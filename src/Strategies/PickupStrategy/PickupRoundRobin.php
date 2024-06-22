<?php
declare(strict_types=1);

namespace CT\AmpPool\Strategies\PickupStrategy;

/**
 * The class implements the strategy of selecting workers in a round-robin manner
 */
final class PickupRoundRobin        extends PickupStrategyAbstract
{
    private array                   $usedWorkers    = [];
    
    public function pickupWorker(
        array $possibleGroups = [],
        array $possibleWorkers = [],
        array $ignoredWorkers = [],
        int   $priority = 0,
        int   $weight = 0,
        int   $tryCount = 0
    ): ?int
    {
        $anyFound                   = false;
        
        // Try to return a worker that has not been used yet
        foreach ($this->iterate($possibleGroups, $possibleWorkers, $ignoredWorkers) as $workerId) {
            
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
        foreach ($this->iterate($possibleGroups, $possibleWorkers, $ignoredWorkers) as $workerId) {
            $this->usedWorkers[]     = $workerId;
            return $workerId;
        }
        
        return null;
    }
}
