<?php
declare(strict_types=1);

namespace IfCastle\AmpPool\Strategies\ScalingStrategy;

/**
 * Worker Scaling Strategy, which defines the rules by which a worker group can be scaled.
 */
interface ScalingStrategyInterface
{
    /**
     * Returns whether the worker group can be scaled.
     *
     *
     */
    public function requestScaling(int $fromWorkerId = 0): bool;
}
