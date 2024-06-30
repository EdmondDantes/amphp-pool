<?php
declare(strict_types=1);

namespace CT\AmpPool\WorkersStorage;

final class WorkersStorageMemory implements WorkersStorageInterface
{
    public static function instanciate(int $workersCount = 0): static
    {
        return new static(WorkerState::class, $workersCount);
    }
    
    private int $key;
    private bool $isWrite           = false;
    private int $structureSize;
    private int         $totalSize;
    private string $buffer          = '';
    
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
        if($this->buffer !== '') {
            return;
        }

        $this->buffer               = \str_repeat("\0", $this->totalSize);
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
    
    public function reviewWorkerState(int $workerId): WorkerStateInterface
    {
        return \forward_static_call([$this->storageClass, 'unpackItem'], $this->readWorkerState($workerId));
    }
    
    public function foreachWorkers(): array
    {
        $this->open();
        
        $workers                    = [];
        
        for($i = 0; $i < $this->workersCount; $i++) {
            $workers[]              = $this->readWorkerState($i + 1);
        }
        
        return $workers;
    }
    
    public function readWorkerState(int $workerId, int $offset = 0): string
    {
        $this->open();
        
        if($workerId < 0) {
            throw new \RuntimeException('Invalid worker id provided');
        }
        
        return \substr($this->buffer, ($workerId - 1) * $this->structureSize + $offset, $this->structureSize);
    }
    
    public function updateWorkerState(int $workerId, string $data, int $offset = 0): void
    {
        $this->open();
        
        if($workerId < 0) {
            throw new \RuntimeException('Invalid worker id provided');
        }
        
        $this->buffer               = \substr_replace(
            $this->buffer, $data, ($workerId - 1) * $this->structureSize + $offset, strlen($data)
        );
    }

    public function close(): void
    {
        $this->buffer               = '';
    }
}