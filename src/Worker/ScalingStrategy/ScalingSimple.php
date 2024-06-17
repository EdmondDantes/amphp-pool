<?php
declare(strict_types=1);

namespace CT\AmpPool\Worker\ScalingStrategy;

use CT\AmpPool\Worker\WorkerStrategyAbstract;

final class ScalingSimple           extends WorkerStrategyAbstract
                                    implements ScalingStrategyInterface
{
    public function requestScaling(int $fromWorkerId = 0): bool
    {
    
    }
    
    public function adjustWorkerCount(): int
    {
        // TODO: Implement adjustWorkerCount() method.
    }
}