<?php
declare(strict_types=1);

namespace CT\AmpPool\Strategies\PickupStrategy;

use CT\AmpPool\Strategies\WorkerStrategyAbstract;

abstract class PickupStrategyAbstract extends WorkerStrategyAbstract implements PickupStrategyInterface
{
    /**
     *
     * @return iterable<\CT\AmpPool\WorkersStorage\WorkerStateInterface>
     */
    protected function iterate(array $possibleGroups = [], array $possibleWorkers = [], array $ignoredWorkers = []): iterable
    {
        foreach ($this->getWorkersStorage()->foreachWorkers() as $workerState) {

            if($possibleWorkers !== [] && false === \in_array($workerState->getWorkerId(), $possibleWorkers, true)
            || $ignoredWorkers !== [] && \in_array($workerState->getWorkerId(), $ignoredWorkers, true)
            || $possibleGroups !== [] && false === \in_array($workerState->getGroupId(), $possibleGroups)) {
                continue;
            }

            yield $workerState;
        }
    }
}
