<?php
declare(strict_types=1);

namespace CT\AmpPool\Worker\PickupStrategy;

use CT\AmpPool\Worker\WorkerDescriptor;
use CT\AmpPool\WorkerTypeEnum;

/**
 * The interface describes the strategy for selecting workers from the pool
 */
interface PickupStrategyInterface
{
    /**
     * Pickup a worker from the pool
     *
     * @param WorkerTypeEnum|null   $workerType
     * @param array|null            $possibleWorkers
     *
     * @return WorkerDescriptor|null
     */
    public function pickupWorker(WorkerTypeEnum $workerType = null, array $possibleWorkers = null): ?WorkerDescriptor;
}