<?php
declare(strict_types=1);

namespace CT\AmpPool\Worker;

use CT\AmpPool\PoolState\PoolStateReadableInterface;
use CT\AmpPool\WorkerGroupInterface;
use CT\AmpPool\WorkerPoolInterface;

abstract class WorkerStrategyAbstract implements WorkerStrategyInterface
{
    private \WeakReference|null $workerPool = null;
    private \WeakReference|null $worker = null;
    private \WeakReference|null $workerGroup = null;
    
    public function getWorkerPool(): WorkerPoolInterface|null
    {
        return $this->workerPool?->get();
    }
    
    public function getWorker(): WorkerInterface|null
    {
        return $this->worker?->get();
    }
    
    public function getWorkerGroup(): WorkerGroupInterface|null
    {
        return $this->workerGroup?->get();
    }
    
    public function setWorkerPool(WorkerPoolInterface $workerPool): self
    {
        $this->workerPool           = \WeakReference::create($workerPool);
        
        return $this;
    }
    
    public function setWorker(WorkerInterface $worker): self
    {
        $this->worker               = \WeakReference::create($worker);
        
        return $this;
    }
    
    public function setWorkerGroup(WorkerGroupInterface $workerGroup): self
    {
        $this->workerGroup          = \WeakReference::create($workerGroup);
        
        return $this;
    }
    
    protected function getGroupsScheme(): array
    {
        if($this->worker?->get() !== null) {
            return $this->worker->get()->getGroupsScheme();
        }
        
        if($this->workerPool?->get() !== null) {
            return $this->workerPool->get()->getGroupsScheme();
        }
        
        return [];
    }
    
    protected function getPoolStateStorage(): ?PoolStateReadableInterface
    {
        if($this->workerPool?->get() !== null) {
            return $this->workerPool->get()->getPoolStateStorage();
        }
        
        if($this->worker?->get() !== null) {
            return $this->worker->get()->getPoolsStateStorage();
        }
        
        return null;
    }
    
    public function __serialize(): array
    {
        return [];
    }
    
    public function __unserialize(array $data): void
    {
    }
}