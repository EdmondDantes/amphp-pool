<?php
declare(strict_types=1);

namespace CT\AmpCluster\PoolState;

/**
 * The class describes the configuration data structure of Workers groups for a pool.
 * The information is in shared memory and is available for reading by different processes.
 */
final class PoolStateStorage
{
    /**
     * Structure of the shared memory area:
     * one item - group info:
     *
     * 1. groupId (int) (32 bit)
     * 2. lowest workerId (int) (32 bit)
     * 3. highest workerId (int) (32 bit)
     * 4. 0 (int) - reserved for future use (32 bit)
     */
    public const int GROUP_INFO_SIZE = 4 * 4;
    
    private int $key;
    private \Shmop|null $shmop      = null;
    /**
     * @var array<int, array<int, int>>
     */
    private array $groups           = [];
    private int $updatedAt          = 0;
    private int $updateInterval     = 5 * 60;
    private bool $isWrite           = false;
    
    public function __construct(private readonly int $groupsCount = 0)
    {
        $this->key                  = ftok(__FILE__, 'p');
        
        if($this->key === -1) {
            throw new \RuntimeException('Failed to generate key ftok');
        }
        
        if($this->groupsCount > 0) {
            $this->isWrite          = true;
            $this->groups           = \array_fill(1, $this->groupsCount, [0, 0, 0, 0]);
        }
    }
    
    public function getStructureSize(): int
    {
        return self::GROUP_INFO_SIZE * $this->groupsCount;
    }
    
    public function getGroups(): array
    {
        $groups                     = [];
        
        foreach ($this->groups as $groupId => [, $lowestWorkerId, $highestWorkerId]) {
            if($groupId <= 0) {
                continue;
            }
            
            $groups[$groupId]       = [$lowestWorkerId, $highestWorkerId];
        }
        
        return $groups;
    }
    
    public function setGroups(array $groups): void
    {
        foreach ($groups as $groupId => [$lowestWorkerId, $highestWorkerId]) {
            
            if($groupId <= 0) {
                continue;
            }
            
            if(\array_key_exists($groupId, $this->groups)) {
                $this->groups[$groupId] = [$groupId, $lowestWorkerId, $highestWorkerId, 0];
            }
        }
        
        $this->commit();
    }
    
    public function setWorkerGroupInfo(int $groupId, int $lowestWorkerId, int $highestWorkerId): void
    {
        if($groupId === 0) {
            throw new \RuntimeException('Group ID is out of range: not allowed to use 0');
        }
        
        if(false === \array_key_exists($groupId, $this->groups)) {
            throw new \RuntimeException('Group ID is out of range');
        }
        
        $this->groups[$groupId]     = [$groupId, $lowestWorkerId, $highestWorkerId, 0];
        $this->commit();
    }
    
    public function findGroupInfo(int $groupId): array
    {
        if($this->updatedAt + $this->updateInterval < \time()) {
            $this->update();
        }
        
        if(\array_key_exists($groupId, $this->groups)) {
            [, $lowestWorkerId, $highestWorkerId] = $this->groups[$groupId];
            return [$lowestWorkerId, $highestWorkerId];
        }
        
        return [0, 0];
    }
    
    public function update(): void
    {
        $data                       = $this->read();
        $data                       = \unpack('L*', $data);
        
        if(false === $data) {
            throw new \RuntimeException('Failed to unpack data');
        }
        
        foreach (\array_chunk($data, 4) as $groupInfo) {
            
            $groupId                = $groupInfo[0] ?? 0;
            $lowestWorkerId         = $groupInfo[1] ?? 0;
            $highestWorkerId        = $groupInfo[2] ?? 0;
            
            if($groupId === 0) {
                continue;
            }
            
            $this->groups[$groupId] = [$groupId, $lowestWorkerId, $highestWorkerId, 0];
        }
    }
    
    private function commit(): void
    {
        $this->write(pack('L*', ...\array_merge(...$this->groups)));
    }
    
    private function open(): void
    {
        \set_error_handler(static function($number, $error, $file = null, $line = null) {
            throw new \ErrorException($error, 0, $number, $file, $line);
        });
        
        try {
            if($this->isWrite) {
                $shmop              = \shmop_open($this->key, 'c', 0644, $this->getStructureSize());
            } else {
                $shmop              = \shmop_open($this->key, 'a', 0, 0);
            }
        } finally {
            \restore_error_handler();
        }
        
        if($shmop === false) {
            throw new \RuntimeException('Failed to open shared memory');
        }
        
        $this->shmop                = $shmop;
    }
    
    private function read(): string
    {
        if($this->shmop === null) {
            $this->open();
        }
        
        $data                       = \shmop_read($this->shmop, 0, 0);
        
        if($data === false) {
            throw new \RuntimeException('Failed to read data from shared memory');
        }
        
        return $data;
    }
    
    private function write(string $data): void
    {
        if(false === $this->isWrite) {
            throw new \RuntimeException('This instance PoolStorage is read-only');
        }
        
        if($this->shmop === null) {
            $this->open();
        }
        
        set_error_handler(static function($number, $error, $file = null, $line = null) {
            throw new \ErrorException($error, 0, $number, $file, $line);
        });
        
        try {
            $count                  = \shmop_write($this->shmop, $data, 0);
        } finally {
            \restore_error_handler();
        }
        
        if($count !== strlen($data)) {
            throw new \RuntimeException('Failed to write data to shared memory: wrote '.$count.' bytes from '.strlen($data));
        }
    }
    
    public function close(): void
    {
        if($this->shmop !== null && $this->isWrite) {
            \shmop_delete($this->shmop);
            $this->shmop            = null;
        }
    }
    
    public function __destruct()
    {
        $this->close();
    }
}