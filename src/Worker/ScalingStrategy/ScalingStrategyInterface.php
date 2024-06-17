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
    
    /**
     * Adjusts the worker count based on the current state of the worker group.
     *
     * @return  int    Returns the number of workers to add or remove.
     * (negative to remove, positive to add, 0 to keep the same number of workers)
     */
    public function adjustWorkerCount(): int;
}