<?php
declare(strict_types=1);

namespace CT\AmpPool\WorkersStorage;

final class WorkersStorage implements WorkersStorageInterface
{
    private int $key;
    private bool $isWrite           = false;
    private int $structureSize;
    private int         $totalSize;
    private \Shmop|null $handler = null;
    
    public function __construct(
        private readonly string $storageClass,
        private readonly int $workersCount = 0
    ) {
        $this->structureSize        = $this->getStructureSize();
        $this->totalSize            = $this->structureSize * $this->workersCount;
        
        $this->key                  = \ftok(__FILE__, 's');
        
        if($this->key === -1) {
            throw new \RuntimeException('Failed to generate key ftok');
        }
        
        if($this->workersCount > 0) {
            $this->isWrite          = true;
        }
    }
    
    private function open(): void
    {
        if($this->handler !== null) {
            return;
        }
        
        \set_error_handler(static function ($number, $error, $file = null, $line = null) {
            throw new \ErrorException($error, 0, $number, $file, $line);
        });
        
        try {
            if($this->isWrite) {
                $handler            = \shmop_open($this->key, 'c', 0644, $this->totalSize);
            } else {
                $handler            = \shmop_open($this->key, 'a', 0, 0);
            }
        } finally {
            \restore_error_handler();
        }
        
        if($handler === false) {
            throw new \RuntimeException('Failed to open shared memory');
        }
        
        $this->handler              = $handler;
    }
    
    private function getStructureSize(): int
    {
        $class                      = $this->storageClass;
        
        if(is_subclass_of($class, WorkerStateInterface::class) === false) {
            throw new \RuntimeException('Invalid storage class provided. Expected ' . WorkerStateInterface::class . ' implementation');
        }
        
        return \forward_static_call([$class, 'getItemSize']);
    }
    
    public function getWorkerState(int $workerId): WorkerStateInterface
    {
        return \forward_static_call([$this->storageClass, 'instanciateFromStorage'], $this, $workerId);
    }
    
    public function readWorkerState(int $workerId, int $offset = 0): string
    {
        if($workerId < 0) {
            throw new \InvalidArgumentException('Invalid worker id provided');
        }
        
        if($this->handler === null) {
            $this->open();
        }
        
        $workerOffset               = $this->structureSize * ($workerId - 1) + $offset;
        
        \set_error_handler(static function ($number, $error, $file = null, $line = null) {
            throw new \ErrorException($error, 0, $number, $file, $line);
        });
        
        try {
            $data                   = \shmop_read($this->handler, $offset, $this->structureSize);
        } finally {
            \restore_error_handler();
        }
        
        if($data === false) {
            throw new \RuntimeException('Failed to read data from shared memory for worker ' . $workerId . ' at offset ' . $offset);
        }
        
        return $data;
    }
    
    public function updateWorkerState(int $workerId, string $data, int $offset = 0): void
    {
        if($workerId < 0) {
            throw new \InvalidArgumentException('Invalid worker id provided');
        }
        
        if(false === $this->isWrite) {
            throw new \RuntimeException('This instance WorkersStorage is read-only');
        }
        
        if($this->handler === null) {
            $this->open();
        }
        
        $workerOffset               = $this->structureSize * ($workerId - 1) + $offset;
        
        \set_error_handler(static function ($number, $error, $file = null, $line = null) {
            throw new \ErrorException($error, 0, $number, $file, $line);
        });
        
        try {
            $count                  = \shmop_write($this->handler, $data, $offset);
        } finally {
            \restore_error_handler();
        }
        
        if($count === false) {
            throw new \RuntimeException('Failed to write data to shared memory for worker ' . $workerId . ' at offset ' . $offset);
        }
    }
    
    public function __destruct()
    {
        if($this->handler !== null && $this->isWrite) {
            \shmop_delete($this->handler);
        }
    }
}