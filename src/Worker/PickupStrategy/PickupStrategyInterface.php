<?php
declare(strict_types=1);

namespace CT\AmpPool\Worker\PickupStrategy;

use CT\AmpPool\Worker\WorkerDescriptor;
use CT\AmpPool\Worker\WorkerInterface;
use CT\AmpPool\WorkerTypeEnum;

/**
 * The interface describes the strategy for selecting workers from the pool
 */
interface PickupStrategyInterface
{
    /**
     * Pickup a worker from the pool.
     *
     * @param array $possibleGroups
     * @param array $possibleWorkers
     * @param array $ignoredWorkers
     *
     * @return int|null
     */
    public function pickupWorker(array $possibleGroups = [], array $possibleWorkers = [], array $ignoredWorkers = []): ?int;
}