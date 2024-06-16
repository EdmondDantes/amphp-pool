<?php
declare(strict_types=1);

namespace CT\AmpPool\Worker\PickupStrategy;

use CT\AmpPool\Worker\WorkerStrategyAbstract;

class PickupStrategyAbstract        extends WorkerStrategyAbstract
                                    implements PickupStrategyInterface
{
    protected function iterate(array $possibleGroups = [], array $possibleWorkers = []): iterable
    {
        $groupsState                = $this->getPoolStateStorage()->getGroupsState();
        
        foreach ($groupsState as $groupId => [$lowestWorkerId, $highestWorkerId]) {
            
            if($possibleGroups !== [] && false === in_array($groupId, $possibleGroups)) {
                continue;
            }
            
            foreach (range($lowestWorkerId, $highestWorkerId) as $workerId) {
                
                if($possibleWorkers !== [] && false === in_array($workerId, $possibleWorkers, true)) {
                    continue;
                }

                yield $workerId;
            }
        }
    }
}