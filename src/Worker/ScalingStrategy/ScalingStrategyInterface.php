<?php
declare(strict_types=1);

namespace CT\AmpPool\Worker\ScalingStrategy;

/**
 * Worker Scaling Strategy, which defines the rules by which a worker group can be scaled.
 */
interface ScalingStrategyInterface
{
    /**
     * Returns whether the worker group can be scaled.
     *
     * @param int $fromWorkerId
     *
     * @return  bool
     */
    public function requestScaling(int $fromWorkerId = 0): bool;
}