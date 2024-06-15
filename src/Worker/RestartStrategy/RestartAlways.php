<?php
declare(strict_types=1);

namespace CT\AmpPool\Worker\RestartStrategy;

use CT\AmpPool\Worker\WorkerStrategyAbstract;
use CT\AmpPool\WorkerGroupInterface;

final class RestartAlways           extends WorkerStrategyAbstract
                                    implements RestartStrategyInterface
{
    public function shouldRestart(mixed $exitResult): int
    {
        return RestartStrategyInterface::RESTART_IMMEDIATELY;
    }
    
    public function getFailReason(): string
    {
        return '';
    }
    
    public function onWorkerStart(int $workerId, WorkerGroupInterface $group): void
    {
    }
}