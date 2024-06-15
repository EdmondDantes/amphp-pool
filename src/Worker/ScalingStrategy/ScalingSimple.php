<?php
declare(strict_types=1);

namespace CT\AmpPool\Worker\ScalingStrategy;

use CT\AmpPool\Worker\WorkerStrategyAbstract;

final class ScalingSimple           extends WorkerStrategyAbstract
                                    implements ScalingStrategyInterface
{
    public function adjustWorkerCount(): int
    {
        // TODO: Implement adjustWorkerCount() method.
    }
}