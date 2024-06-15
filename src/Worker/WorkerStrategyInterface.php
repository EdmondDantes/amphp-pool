<?php
declare(strict_types=1);

namespace CT\AmpCluster\Worker;

use CT\AmpCluster\WorkerGroupInterface;
use CT\AmpCluster\WorkerPoolInterface;

interface WorkerStrategyInterface
{
    public function getWorkerPool(): WorkerPoolInterface|null;
    public function getWorker(): WorkerInterface|null;
    public function getWorkerGroup(): WorkerGroupInterface|null;
    
    public function setWorkerPool(WorkerPoolInterface $workerPool): self;
    
    public function setWorker(WorkerInterface $worker): self;
    
    public function setWorkerGroup(WorkerGroupInterface $workerGroup): self;
}