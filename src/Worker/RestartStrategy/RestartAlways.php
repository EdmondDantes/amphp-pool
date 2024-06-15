<?php
declare(strict_types=1);

namespace CT\AmpPool\Worker\RestartStrategy;

use CT\AmpPool\Worker\WorkerStrategyAbstract;

final class RestartAlways           extends WorkerStrategyAbstract
                                    implements RestartStrategyInterface
{
    public function shouldRestart(mixed $exitResult): int
    {
        // TODO: Implement shouldRestart() method.
    }
    
    public function getFailReason(): string
    {
        // TODO: Implement getFailReason() method.
    }
}