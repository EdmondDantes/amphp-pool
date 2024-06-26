<?php
declare(strict_types=1);

namespace CT\AmpPool\Worker\Internal;

use Amp\Future;
use CT\AmpPool\Internal\WorkerProcessContext;
use CT\AmpPool\WorkerGroup;

/**
 * @internal
 */
final class WorkerDescriptor
{
    private ?WorkerProcessContext $workerProcess    = null;
    private bool                  $isStoppedForever = false;
    
    public function __construct(
        public readonly int $id,
        public readonly WorkerGroup $group,
        private bool $shouldBeStarted = false
    ) {}
    
    public function getWorkerProcess(): ?WorkerProcessContext
    {
        return $this->workerProcess;
    }
    
    public function setWorkerProcess(WorkerProcessContext $workerProcess): void
    {
        $this->workerProcess        = $workerProcess;
    }
    
    public function markAsStopped(): void
    {
        $this->workerProcess        = null;
    }
    
    public function isRunning(): bool
    {
        return $this->workerProcess !== null && false === $this->workerProcess->wasTerminated();
    }
    
    public function isNotRunning(): bool
    {
        return $this->workerProcess === null || $this->workerProcess->wasTerminated();
    }
    
    public function isStoppedForever(): bool
    {
        return $this->isStoppedForever;
    }
    
    public function shouldBeStarted(): bool
    {
        return true === $this->shouldBeStarted && false === $this->isStoppedForever;
    }
    
    public function markAsStoppedForever(): void
    {
        $this->workerProcess        = null;
        $this->isStoppedForever     = true;
    }
}