<?php
declare(strict_types=1);

namespace CT\AmpPool\Worker;

use CT\AmpPool\Worker\PickupStrategy\PickupStrategyInterface;
use CT\AmpPool\Worker\ScalingStrategy\ScalingStrategyInterface;
use CT\AmpPool\Worker\RestartStrategy\RestartStrategyInterface;

final readonly class WorkerStrategies
{
    public function __construct(
        public PickupStrategyInterface  $pickupStrategy,
        public ScalingStrategyInterface $scalingStrategy,
        public RestartStrategyInterface $restartStrategy,
    ) {}
}