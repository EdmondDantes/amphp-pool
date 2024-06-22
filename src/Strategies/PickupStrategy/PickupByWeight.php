<?php
declare(strict_types=1);

namespace CT\AmpPool\Strategies\PickupStrategy;

/**
 * Selects a worker with the smallest weight.
 * Each JOB has its own weight coefficient (integer number great zero).
 * When a Worker starts performing a job, it increases the total weight parameter.
 * The higher the weight of the worker, the more complex jobs they are handling.
 *
 * This algorithm selects the Worker with the lowest possible weight.
 */
class PickupByWeight                extends PickupStrategyAbstract
{
    private int $lastMinimalWeight  = 0;
    
    public function __construct(private readonly int $ignoreWeightLimit = 0) {}
    
    public function pickupWorker(
        array $possibleGroups       = [],
        array $possibleWorkers      = [],
        array $ignoredWorkers       = [],
        int   $priority             = 0,
        int   $weight               = 0,
        int   $tryCount             = 0
    ): ?int
    {
        $workersInfo                = $this->getWorkersInfo();
        
        if($workersInfo === null) {
            return null;
        }
        
        $foundWorkerId              = null;
        $minimalWeight              = 0;
        
        foreach ($this->iterate($possibleGroups, $possibleWorkers, $ignoredWorkers) as $workerId) {
            
            $workerState            = $workersInfo->getWorkerState($workerId);
            
            if($workerState === null) {
                continue;
            }
            
            if($workerState->isReady() === false) {
                continue;
            }
            
            // If the worker's weight is greater than the limit and $tryCount === 0, we ignore it.
            if($this->ignoreWeightLimit > 0 && $workerState->getWorkerWeight() > $this->ignoreWeightLimit && $tryCount === 0) {
                continue;
            }
            
            if($workerState->getWorkerWeight() === 0 || $workerState->getJobCount() === 0) {
                return $workerId;
            }
            
            if($workerState->getWorkerWeight() < $minimalWeight) {
                $minimalWeight      = $workerState->getWorkerWeight();
                $foundWorkerId      = $workerId;
            }
        }
        
        $this->lastMinimalWeight    = $foundWorkerId === null ? $minimalWeight : 0;
        
        return $foundWorkerId;
    }
    
    public function getLastMinimalWeight(): int
    {
        return $this->lastMinimalWeight;
    }
}