<?php
declare(strict_types=1);

namespace CT\AmpPool\Strategies\PickupStrategy;

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
     * @param int   $tryCount
     *
     * @return int|null
     */
    public function pickupWorker(array $possibleGroups = [], array $possibleWorkers = [], array $ignoredWorkers = [], int $tryCount = 0): ?int;
}