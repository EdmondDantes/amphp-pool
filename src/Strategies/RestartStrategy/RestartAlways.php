<?php
declare(strict_types=1);

namespace CT\AmpPool\Strategies\RestartStrategy;

use CT\AmpPool\Strategies\WorkerStrategyAbstract;

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
}