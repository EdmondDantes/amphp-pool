<?php
declare(strict_types=1);

namespace IfCastle\AmpPool\WorkerPoolMocks;

use IfCastle\AmpPool\Exceptions\TerminateWorkerException;
use IfCastle\AmpPool\Worker\WorkerEntryPointInterface;
use IfCastle\AmpPool\Worker\WorkerInterface;

final class TerminateWorkerEntryPoint implements WorkerEntryPointInterface
{
    public function initialize(WorkerInterface $worker): void
    {
    }

    public function run(): void
    {
        throw new TerminateWorkerException();
    }
}
