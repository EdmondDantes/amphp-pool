<?php
declare(strict_types=1);

namespace CT\AmpPool\Strategies;

use CT\AmpPool\PoolState\PoolStateReadableInterface;
use CT\AmpPool\Worker\WorkerInterface;
use CT\AmpPool\Worker\WorkerState\WorkersInfoInterface;
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
    
    protected function getWorkersInfo(): ?WorkersInfoInterface
    {
        if($this->workerPool?->get() !== null) {
            return $this->workerPool->get()->getWorkersInfo();
        }
        
        if($this->worker?->get() !== null) {
            return $this->worker->get()->getWorkersInfo();
        }
        
        return null;
    }
    
    protected function getCurrentWorkerId(): ?int
    {
        if($this->worker?->get() !== null) {
            return $this->worker->get()->getWorkerId();
        }
        
        return null;
    }
    
    protected function isWorker(): bool
    {
        return $this->worker !== null && $this->workerPool === null;
    }
    
    protected function isWatcher(): bool
    {
        return $this->worker === null && $this->workerPool !== null;
    }
    
    public function onStarted(): void
    {
    }
    
    public function onStopped(): void
    {
    }
    
    public function __serialize(): array
    {
        return [];
    }
    
    public function __unserialize(array $data): void
    {
    }
}