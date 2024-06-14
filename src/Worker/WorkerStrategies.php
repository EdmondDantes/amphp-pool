<?php
declare(strict_types=1);

namespace CT\AmpCluster\Worker;

use CT\AmpCluster\Worker\PickupStrategy\PickupStrategyInterface;
use CT\AmpCluster\Worker\ScalingStrategy\ScalingStrategyInterface;
use CT\AmpCluster\Worker\RestartStrategy\RestartStrategyInterface;

final readonly class WorkerStrategies
{
    public function __construct(
        public PickupStrategyInterface  $pickupStrategy,
        public ScalingStrategyInterface $scalingStrategy,
        public RestartStrategyInterface $restartStrategy,
    ) {}
}