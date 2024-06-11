<?php
declare(strict_types=1);

namespace CT\AmpServer\PickupWorkerStrategy;

use CT\AmpServer\Worker\WorkerDescriptor;
use CT\AmpServer\WorkerTypeEnum;

/**
 * The interface describes the strategy for selecting workers from the pool
 */
interface PickupWorkerStrategyI
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