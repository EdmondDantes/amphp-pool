<?php
declare(strict_types=1);

namespace CT\AmpPool\WorkersStorage;

class ApplicationState implements ApplicationStateInterface
{
    private \WeakReference|null $storage = null;
    
    public static function instanciate(WorkersStorageInterface $workersStorage, int $workersCount): static
    {
        $instance                   = new static($workersCount);
        $instance->storage          = \WeakReference::create($workersStorage);
        
        return $instance;
    }
    
    public function __construct(
        private int $workersCount       = 0,
        private int $startedAt          = 0,
        private int $lastRestartedAt    = 0,
        private int $restartsCount      = 0,
        private int $workersErrors      = 0,
        private int $memoryFree         = 0,
        private int $memoryTotal        = 0,
        private float $loadAverage      = 0.0
    ) {}
    
    public function update(): void
    {
        $storage                    = $this->getStorage();
        
        if($storage === null) {
            return;
        }
        
        $this->startedAt            = $storage->getStartedAt();
        $this->lastRestartedAt      = $storage->getApplicationState()->getLastRestartedAt();
        $this->restartsCount        = $storage->getApplicationState()->getRestartsCount();
        $this->workersErrors        = $storage->getApplicationState()->getWorkersErrors();
        $this->memoryFree           = $storage->getMemoryUsage()->getWorkersMemoryUsageStat()['free'];
        $this->memoryTotal          = $storage->getMemoryUsage()->getWorkersMemoryUsageStat()['total'];
        $this->loadAverage          = \sys_getloadavg()[0];
    }
    
    public function read(): void
    {
        $storage                    = $this->getStorage();
        
        if($storage === null) {
            return;
        }

        
    }
    
    public function getStructureSize(): int
    {
        return 8 * 8;
    }
    
    public function getWorkersCount(): int
    {
        return $this->workersCount;
    }
    
    public function getUptime(): int
    {
        return \time() - $this->startedAt;
    }
    
    public function getStartedAt(): int
    {
        return $this->startedAt;
    }
    
    public function getLastRestartedAt(): int
    {
        return $this->lastRestartedAt;
    }
    
    public function getRestartsCount(): int
    {
        return $this->restartsCount;
    }
    
    public function getWorkersErrors(): int
    {
        return $this->workersErrors;
    }
    
    public function getMemoryFree(): int
    {
        return $this->memoryFree;
    }
    
    public function getMemoryTotal(): int
    {
        return $this->memoryTotal;
    }
    
    public function getLoadAverage(): float
    {
        return $this->loadAverage;
    }
    
    private function getStorage(): WorkersStorageInterface|null
    {
        return $this->storage?->get();
    }
}