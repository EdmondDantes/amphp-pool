<?php
declare(strict_types=1);

namespace CT\AmpPool\Worker\RestartStrategy;

use CT\AmpPool\WorkerGroupInterface;

final class RestartNever implements RestartStrategyInterface
{
    public function shouldRestart(mixed $exitResult): int
    {
        return RestartStrategyInterface::RESTART_NEVER;
    }
    
    public function getFailReason(): string
    {
        return 'Worker should never be restarted';
    }
    
    public function onWorkerStart(int $workerId, WorkerGroupInterface $group): void
    {
    }
}