<?php
declare(strict_types=1);

namespace IfCastle\AmpPool\Strategies\PickupStrategy;

/**
 * The interface describes the strategy for selecting workers from the pool.
 */
interface PickupStrategyInterface
{
    /**
     * Pickup a worker from the pool.
     *
     *
     */
    public function pickupWorker(
        array $possibleGroups       = [],
        array $possibleWorkers      = [],
        array $ignoredWorkers       = [],
        int   $priority             = 0,
        int   $weight               = 0,
        int   $tryCount             = 0
    ): ?int;
}
