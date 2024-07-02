<?php
declare(strict_types=1);

namespace Examples\Prometheus;

use CT\AmpPool\Worker\WorkerEntryPointInterface;
use CT\AmpPool\Worker\WorkerInterface;

final class JobWorker implements WorkerEntryPointInterface
{
    public function initialize(WorkerInterface $worker): void
    {
        // TODO: Implement initialize() method.
    }
    
    public function run(): void
    {
        // TODO: Implement run() method.
    }
}