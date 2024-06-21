<?php
declare(strict_types=1);

namespace CT\AmpPool\Strategies\PickupStrategy;

/**
 * The algorithm selects a worker based on the number of tasks assigned to them.
 * One worker can handle multiple tasks.
 * We take into account how many tasks have been assigned to each worker
 * and use the one that is currently handling the minimum number of tasks.
 *
 */
final class PickupLeastJobs         extends PickupStrategyAbstract
{
    public function pickupWorker(array $possibleGroups = [], array $possibleWorkers = [], array $ignoredWorkers = []): ?int
    {
        $workersInfo                = $this->getWorkersInfo();
        
        if($workersInfo === null) {
            return null;
        }
        
        $foundWorkerId              = null;
        $bestJobCount               = 0;
        
        foreach ($this->iterate($possibleGroups, $possibleWorkers, $ignoredWorkers) as $workerId) {
            
            $workerState            = $workersInfo->getWorkerState($workerId);
            
            if($workerState === null) {
                continue;
            }
            
            if($workerState->isReady() === false) {
                continue;
            }
            
            if($workerState->getJobCount() === 0) {
                return $workerId;
            }
            
            if($workerState->getJobCount() < $bestJobCount) {
                $bestJobCount       = $workerState->getJobCount();
                $foundWorkerId      = $workerId;
            }
        }
        
        return $foundWorkerId;
    }
}