<?php
declare(strict_types=1);

namespace CT\AmpPool\Worker\Internal;

use Amp\DeferredFuture;
use Amp\Future;
use CT\AmpPool\Internal\WorkerProcessContext;
use CT\AmpPool\WorkerGroup;

/**
 * @internal
 */
final class WorkerDescriptor
{
    private ?DeferredFuture       $startFuture      = null;
    private ?WorkerProcessContext $workerProcess    = null;
    private bool                  $isStoppedForever = false;
    
    public function __construct(
        public readonly int $id,
        public readonly WorkerGroup $group,
        private bool $shouldBeStarted = false
    ) {}
    
    public function starting(): void
    {
        $this->started();
        $this->startFuture          = new DeferredFuture;
    }
    
    public function started(): void
    {
        if($this->startFuture?->isComplete() === false) {
            $this->startFuture->complete();
        }
    }
    
    public function getStartDeferred(): DeferredFuture|null
    {
        return $this->startFuture;
    }
    
    public function getStartFuture(): Future|null
    {
        return $this->startFuture?->getFuture();
    }
    
    public function getWorkerProcess(): ?WorkerProcessContext
    {
        return $this->workerProcess;
    }
    
    public function setWorkerProcess(WorkerProcessContext $workerProcess): void
    {
        $this->workerProcess        = $workerProcess;
    }
    
    public function willBeStarted(): void
    {
        $this->shouldBeStarted      = true;
        
        if($this->startFuture?->isComplete() === false) {
            $this->startFuture->complete();
        }
        
        $this->startFuture          = new DeferredFuture;
    }
    
    public function willBeStopped(): void
    {
        $this->shouldBeStarted      = false;
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
        $this->isStoppedForever     = true;
    }
}