<?php
declare(strict_types=1);

namespace CT\AmpPool\JobIpc;

use CT\AmpPool\Strategies\PickupStrategy\PickupStrategyInterface;

/**
 * Always returns the same workerId.
 */
final readonly class PickupStrategyDummy implements PickupStrategyInterface
{
    public function __construct(public int $workerId)
    {
    }

    public function pickupWorker(
        array $possibleGroups       = [],
        array $possibleWorkers      = [],
        array $ignoredWorkers       = [],
        int   $priority             = 0,
        int   $weight               = 0,
        int   $tryCount             = 0
    ): ?int {
        return $this->workerId;
    }
}
