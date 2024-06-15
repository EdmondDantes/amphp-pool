<?php
declare(strict_types=1);

namespace CT\AmpCluster\Worker\RestartStrategy;

use CT\AmpCluster\Worker\WorkerStrategyAbstract;

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