<?php
declare(strict_types=1);

namespace CT\AmpServer\WorkerState;

use CT\AmpServer\WorkerState\Exceptions\WorkerStateNotAvailable;
use CT\AmpServer\WorkerState\Exceptions\WorkerStateReadFailed;

/**
 * The class creates an entry in the shared memory area where it writes the state of the Worker, which can be read by another process.
 *
 * The state includes two variables:
 *
 * Whether the worker is ready to accept incoming JOBs.
 * The number of JOBs the worker is currently processing.
 */
final class WorkerStateStorage
{
    private int $key;
    private \Shmop|null $shmop      = null;
    
    private bool $isReady           = true;
    private int $jobCount           = 0;
    
    public function __construct(private readonly int $workerId, private int $groupId = 0, private readonly bool $isWrite = false)
    {
        $this->key                  = \ftok(__FILE__, pack('c', $workerId));
        
        if($this->key === -1) {
            throw new \RuntimeException('Failed to generate key ftok');
        }
    }
    
    public function getWorkerId(): int
    {
        return $this->workerId;
    }
    
    public function getWorkerGroupId(): int
    {
        return $this->groupId;
    }
    
    public function workerReady(): void
    {
        $this->isReady              = true;
        $this->commit();
    }
    
    public function workerBusy(): void
    {
        $this->isReady              = false;
        $this->commit();
    }
    
    public function isWorkerReady(): bool
    {
        return $this->isReady;
    }
    
    public function getJobCount(): int
    {
        return $this->jobCount;
    }
    
    public function incrementJobCount(): void
    {
        $this->jobCount++;
        $this->commit();
    }
    
    public function decrementJobCount(): void
    {
        $this->jobCount--;
        $this->commit();
    }
    
    public function update(): void
    {
        $data                       = $this->read();
        
        if(strlen($data) !== WorkerState::SIZE) {
            return;
        }
        
        $result                     = \unpack('L*', $data);
        
        if(false === $result || count($result) !== 4) {
            throw new \RuntimeException('Failed to unpack data');
        }
        
        [, $isReady, $this->jobCount, $this->groupId] = $result;
        
        $this->isReady              = (bool)$isReady;
    }
    
    private function commit(): void
    {
        $this->write(\pack('L*', (int)$this->isReady, $this->jobCount, $this->groupId));
    }
    
    private function open(): void
    {
        \set_error_handler(static function($number, $error, $file = null, $line = null) {
            throw new WorkerStateNotAvailable($error, 0, new \ErrorException($error, 0, $number, $file, $line));
        });
        
        try {
            if($this->isWrite) {
                $shmop              = \shmop_open($this->key, 'c', 0644, WorkerState::SIZE);
            } else {
                $shmop              = \shmop_open($this->key, 'a', 0, 0);
            }
        } finally {
            \restore_error_handler();
        }
        
        if($shmop === false) {
            throw new WorkerStateNotAvailable('Failed to open shared memory');
        }
        
        $this->shmop                = $shmop;
    }
    
    private function read(): string
    {
        if($this->isWrite) {
            throw new \RuntimeException('This instance WorkersStateStorage is write-only');
        }
        
        if($this->shmop === null) {
            $this->open();
        }
        
        $data                       = \shmop_read($this->shmop, 0, WorkerState::SIZE);
        
        if($data === false) {
            throw new WorkerStateReadFailed('Failed to read data from shared memory');
        }
        
        return $data;
    }
    
    private function write(string $data): void
    {
        if(false === $this->isWrite) {
            throw new \RuntimeException('This instance WorkersStateStorage is read-only');
        }
        
        if($this->shmop === null) {
            $this->open();
        }
        
        if(\shmop_write($this->shmop, $data, 0) !== strlen($data)) {
            throw new \RuntimeException('Failed to write data to shared memory');
        }
    }
    
    public function close(): void
    {
        if($this->shmop !== null && $this->isWrite) {
            \shmop_delete($this->shmop);
        }
    }
    
    public function __destruct()
    {
        $this->close();
    }
}