<?php
declare(strict_types=1);

namespace CT\AmpPool\WorkersStorage;

final class WorkersStorage implements WorkersStorageInterface
{
    public static function instanciate(int $workersCount = 0): static
    {
        return new static(
            WorkerState::class, ApplicationState::class, MemoryUsage::class, $workersCount
        );
    }

    private int $key;
    private bool $isWrite           = false;
    private int $structureSize;
    private int         $totalSize;
    private \Shmop|null $handler = null;
    
    private ApplicationStateInterface|null $applicationState = null;
    private MemoryUsageInterface|null $memoryUsage = null;

    public function __construct(
        private readonly string $storageClass,
        private readonly string $applicationClass,
        private readonly string $memoryUsageClass,
        private int $workersCount   = 0,
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

        if(\is_subclass_of($class, WorkerStateInterface::class) === false) {
            throw new \RuntimeException('Invalid storage class provided. Expected ' . WorkerStateInterface::class . ' implementation');
        }

        return \forward_static_call([$class, 'getItemSize']);
    }

    public function getWorkerState(int $workerId): WorkerStateInterface
    {
        $this->validateWorkerId($workerId);

        return \forward_static_call([$this->storageClass, 'instanciateFromStorage'], $this, $workerId);
    }

    public function reviewWorkerState(int $workerId): WorkerStateInterface
    {
        $this->validateWorkerId($workerId);

        return \forward_static_call([$this->storageClass, 'unpackItem'], $this->readWorkerState($workerId));
    }

    public function foreachWorkers(): array
    {
        $this->open();

        // get all data from shared memory
        \set_error_handler(static function ($number, $error, $file = null, $line = null) {
            throw new \ErrorException($error, 0, $number, $file, $line);
        });

        try {
            $data                   = \shmop_read($this->handler, 0, \shmop_size($this->handler));
        } finally {
            \restore_error_handler();
        }

        if($data === false) {
            throw new \RuntimeException('Failed to read Workers data from shared memory');
        }

        $workersCount               = (int) (\strlen($data) / $this->structureSize);

        if($this->workersCount === 0) {
            $this->workersCount     = $workersCount;
        } elseif($this->workersCount !== $workersCount) {
            throw new \RuntimeException('Invalid workers count in shared memory');
        }

        $workers                    = [];

        for($i = 0; $i < $workersCount; $i++) {
            $workers[]              = \forward_static_call(
                [$this->storageClass, 'unpackItem'],
                \substr($data, $i * $this->structureSize, $this->structureSize)
            );
        }

        return $workers;
    }

    public function readWorkerState(int $workerId, int $offset = 0): string
    {
        $this->validateWorkerId($workerId);

        if($this->handler === null) {
            $this->open();
        }

        $workerOffset               = $this->calculateWorkerOffset($workerId);

        \set_error_handler(static function ($number, $error, $file = null, $line = null) {
            throw new \ErrorException($error, 0, $number, $file, $line);
        });

        try {
            $data                   = \shmop_read(
                $this->handler,
                $workerOffset + $offset,
                $this->calculateSize($workerOffset, $offset, $this->structureSize)
            );
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
        $this->validateWorkerId($workerId);

        if(false === $this->isWrite) {
            throw new \RuntimeException('This instance WorkersStorage is read-only');
        }

        if($this->handler === null) {
            $this->open();
        }

        $workerOffset               = $this->calculateWorkerOffset($workerId);

        \set_error_handler(static function ($number, $error, $file = null, $line = null) {
            throw new \ErrorException($error, 0, $number, $file, $line);
        });

        try {
            // validate size of data
            $this->calculateSize($workerOffset, $offset, \strlen($data));

            $count                  = \shmop_write($this->handler, $data, $workerOffset + $offset);
        } finally {
            \restore_error_handler();
        }

        if($count === false) {
            throw new \RuntimeException('Failed to write data to shared memory for worker ' . $workerId . ' at offset ' . $offset);
        }
    }

    public function getApplicationState(): ApplicationStateInterface
    {
        if($this->applicationState !== null) {
            return $this->applicationState;
        }
        
        $this->applicationState     = \forward_static_call([$this->applicationClass, 'instanciate'], $this);
        
        return $this->applicationState;
    }
    
    public function readApplicationState(): string
    {
        $size                       = $this->getApplicationState()->getStructureSize();
        
        if($this->handler === null) {
            $this->open();
        }
        
        if($size < \shmop_size($this->handler)) {
            throw new \RuntimeException('Shared memory segment is too small for ApplicationState data');
        }
        
        \set_error_handler(static function ($number, $error, $file = null, $line = null) {
            throw new \ErrorException($error, 0, $number, $file, $line);
        });
        
        try {
            $data                   = \shmop_read($this->handler, 0, $size);
        } finally {
            \restore_error_handler();
        }
        
        if($data === false) {
            throw new \RuntimeException('Failed to read ApplicationState data from shared memory');
        }
        
        return $data;
    }
    
    public function getMemoryUsage(): MemoryUsageInterface
    {
        if($this->memoryUsage !== null) {
            return $this->memoryUsage;
        }
        
        $this->memoryUsage          = \forward_static_call([$this->memoryUsageClass, 'instanciate'], $this, $this->workersCount);
        
        return $this->memoryUsage;
    }
    
    public function readMemoryUsage(): string
    {
        // The memory usage data is stored after the ApplicationState data
        $offset                     = $this->getApplicationState()->getStructureSize();
        $size                       = $this->getMemoryUsage()->getStructureSize();
        
        if($this->handler === null) {
            $this->open();
        }
        
        if(($offset + $size) < \shmop_size($this->handler)) {
            throw new \RuntimeException('Shared memory segment is too small for MemoryUsage data: '
                                        . \shmop_size($this->handler) . ' < ' . ($offset + $size) . ' bytes');
        }
        
        \set_error_handler(static function ($number, $error, $file = null, $line = null) {
            throw new \ErrorException($error, 0, $number, $file, $line);
        });
        
        try {
            $data                   = \shmop_read($this->handler, $offset, $size);
        } finally {
            \restore_error_handler();
        }
        
        if($data === false) {
            throw new \RuntimeException('Failed to read MemoryUsage data from shared memory');
        }
        
        return $data;
    }
    
    public function close(): void
    {
        if($this->handler !== null && $this->isWrite) {
            \shmop_delete($this->handler);
        }
    }

    public function __destruct()
    {
        $this->close();
    }

    private function validateWorkerId(int $workerId): void
    {
        if($workerId <= 0) {
            throw new \InvalidArgumentException('Invalid worker id provided');
        }

        if($this->workersCount !== 0 && $workerId > $this->workersCount) {
            throw new \InvalidArgumentException('Worker id is out of range');
        }
    }

    private function calculateWorkerOffset(int $workerId): int
    {
        $this->validateWorkerId($workerId);

        //$basicOffset                = $this->getApplicationState()->getStructureSize() + $this->getMemoryUsage()->getStructureSize();
        $offset                     = $this->structureSize * ($workerId - 1);

        // out of range
        if($offset >= \shmop_size($this->handler)) {
            throw new \InvalidArgumentException('Worker id is out of range');
        }

        return $this->structureSize * ($workerId - 1);
    }

    private function calculateSize(int $workerOffset, int $offset, int $size): int
    {
        $totalSize              = \shmop_size($this->handler);

        // $workerOffset + $offset + $this->structureSize
        // the sum of offset and size must be less than or equal to the actual size of the shared memory segment.

        if($workerOffset + $offset + $size > $totalSize) {
            $size               = $totalSize - $workerOffset - $offset;
        }

        if($size <= 0) {
            throw new \RuntimeException('Invalid size of data to read');
        }

        return $size;
    }
}
