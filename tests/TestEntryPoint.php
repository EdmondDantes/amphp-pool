<?php
declare(strict_types=1);

namespace CT\AmpPool;

use CT\AmpPool\Worker\WorkerEntryPointInterface;
use CT\AmpPool\Worker\WorkerInterface;

final class TestEntryPoint implements WorkerEntryPointInterface
{
    private WorkerInterface $workerStrategy;
    
    public function initialize(WorkerInterface $worker): void
    {
        $this->workerStrategy        = $worker;
    }
    
    public function run(): void
    {
    }
}