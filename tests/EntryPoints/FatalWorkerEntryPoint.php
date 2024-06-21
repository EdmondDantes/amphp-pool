<?php
declare(strict_types=1);

namespace CT\AmpPool\EntryPoints;

use CT\AmpPool\Worker\WorkerEntryPointInterface;
use CT\AmpPool\Worker\WorkerInterface;

final class FatalWorkerEntryPoint implements WorkerEntryPointInterface
{
    public function initialize(WorkerInterface $worker): void
    {
    }
    
    public function run(): void
    {
        throw new \RuntimeException('Fatal error');
    }
}