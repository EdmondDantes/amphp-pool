<?php
declare(strict_types=1);

namespace CT\AmpPool\Worker\PickupStrategy;

use CT\AmpPool\Worker\WorkerDescriptor;
use CT\AmpPool\Worker\WorkerInterface;
use CT\AmpPool\Worker\WorkerStrategyAbstract;
use CT\AmpPool\WorkerPoolInterface;
use CT\AmpPool\WorkerTypeEnum;

/**
 * The class implements the strategy of selecting workers in a round-robin manner
 */
final class PickupRoundRobin        extends PickupStrategyAbstract
{
    private array                   $usedWorkers    = [];
    
    public function pickupWorker(array $possibleGroups = [], array $possibleWorkers = []): ?int
    {
        $anyFound                   = false;
        
        // Try to return a worker that has not been used yet
        foreach ($this->iterate($possibleGroups, $possibleWorkers) as $workerId) {
            
            $anyFound               = true;
            
            if(false === in_array($workerId, $this->usedWorkers, true)) {
                $this->usedWorkers[] = $workerId;
                return $workerId;
            }
        }
        
        if(false === $anyFound) {
            return null;
        }
        
        $this->usedWorkers          = [];
        
        // Returns first available worker
        foreach ($this->iterate($possibleGroups, $possibleWorkers) as $workerId) {
            $this->usedWorkers[]     = $workerId;
            return $workerId;
        }
        
        return null;
    }
}
