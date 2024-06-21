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
    private ?WorkerProcessContext $workerProcess = null;
    private ?Future               $future        = null;
    private bool                  $stopped       = false;
    
    public function __construct(
        public readonly int $id,
        public readonly WorkerGroup $group,
        public readonly bool $shouldBeStarted = false
    ) {}
    
    public function getWorkerProcess(): ?WorkerProcessContext
    {
        return $this->workerProcess;
    }
    
    public function setWorkerProcess(WorkerProcessContext $workerProcess): void
    {
        $this->workerProcess        = $workerProcess;
    }
    
    public function getFuture(): ?Future
    {
        return $this->future;
    }
    
    public function setFuture(Future $future): void
    {
        $this->future               = $future;
    }
    
    public function isStopped(): bool
    {
        return $this->stopped;
    }
    
    public function markAsStopped(): void
    {
        $this->stopped              = true;
    }
    
    public function reset(): void
    {
        $this->workerProcess = null;
        $this->future        = null;
    }
}