<?php
declare(strict_types=1);

namespace CT\AmpCluster;

use CT\AmpCluster\Worker\WorkerEntryPointInterface;
use CT\AmpCluster\Worker\WorkerInterface;

final class TestEntryPoint implements WorkerEntryPointInterface
{
    private WorkerInterface $workerStrategy;
    
    public function initialize(WorkerInterface $workerStrategy): void
    {
        $this->workerStrategy        = $workerStrategy;
    }
    
    public function run(): void
    {
    
    }
}