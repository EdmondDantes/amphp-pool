<?php
declare(strict_types=1);

namespace CT\AmpCluster\Worker\ScalingStrategy;

use CT\AmpCluster\Worker\WorkerStrategyAbstract;

final class ScalingSimple           extends WorkerStrategyAbstract
                                    implements ScalingStrategyInterface
{
    public function adjustWorkerCount(): int
    {
        // TODO: Implement adjustWorkerCount() method.
    }
}