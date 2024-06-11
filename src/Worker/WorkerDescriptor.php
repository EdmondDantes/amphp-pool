<?php
declare(strict_types=1);

namespace CT\AmpServer\Worker;

use Amp\Future;
use CT\AmpServer\WorkerProcessContext;
use CT\AmpServer\WorkerTypeEnum;

class WorkerDescriptor
{
    protected ?WorkerProcessContext $worker = null;
    protected ?Future               $future = null;
    
    public function __construct(
        public readonly int $id,
        public readonly WorkerTypeEnum $type,
        public readonly int $groupId,
        public readonly string $entryPointClassName
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