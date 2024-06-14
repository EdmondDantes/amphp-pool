<?php
declare(strict_types=1);

namespace CT\AmpCluster\Worker;

use Amp\Future;
use CT\AmpCluster\WorkerGroup;
use CT\AmpCluster\WorkerProcessContext;
use CT\AmpCluster\WorkerTypeEnum;

final class WorkerDescriptor
{
    protected ?WorkerProcessContext $worker = null;
    protected ?Future               $future = null;
    
    public function __construct(
        public readonly int $id,
        public readonly WorkerGroup $group,
        public readonly WorkerStrategies $strategies,
        public readonly bool $shouldBeStarted = false
    ) {}
    
    public function getWorker(): ?WorkerProcessContext
    {
        return $this->worker;
    }
    
    public function setWorker(WorkerProcessContext $worker): void
    {
        $this->worker               = $worker;
    }
    
    public function getFuture(): ?Future
    {
        return $this->future;
    }
    
    public function setFuture(Future $future): void
    {
        $this->future               = $future;
    }
    
    public function reset(): void
    {
        $this->worker               = null;
        $this->future               = null;
    }
}