<?php
declare(strict_types=1);

namespace CT\AmpPool\Worker;

use Amp\Future;
use CT\AmpPool\WorkerGroup;
use CT\AmpPool\WorkerProcessContext;
use CT\AmpPool\WorkerTypeEnum;

final class WorkerDescriptor
{
    protected ?WorkerProcessContext $worker = null;
    protected ?Future               $future = null;
    
    public function __construct(
        public readonly int $id,
        public readonly WorkerGroup $group,
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