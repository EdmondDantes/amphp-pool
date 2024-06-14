<?php
declare(strict_types=1);

namespace CT\AmpCluster\PickupWorkerStrategy;

use CT\AmpCluster\Worker\WorkerDescriptor;
use CT\AmpCluster\WorkerTypeEnum;

/**
 * The interface describes the strategy for selecting workers from the pool
 */
interface PickupWorkerStrategyInterface
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