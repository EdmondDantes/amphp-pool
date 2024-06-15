<?php
declare(strict_types=1);

namespace CT\AmpCluster\Worker;

use CT\AmpCluster\WorkerGroupInterface;
use CT\AmpCluster\WorkerPoolInterface;

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
    
    public function __serialize(): array
    {
        return [];
    }
    
    public function __unserialize(array $data): void
    {
    }
}