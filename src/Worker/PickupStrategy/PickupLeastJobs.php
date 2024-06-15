<?php
declare(strict_types=1);

namespace CT\AmpPool\Worker\PickupStrategy;

use CT\AmpPool\Worker\WorkerDescriptor;
use CT\AmpPool\Worker\WorkerStrategyAbstract;
use CT\AmpPool\WorkerTypeEnum;

/**
 * The algorithm selects a worker based on the number of tasks assigned to them.
 * One worker can handle multiple tasks.
 * We take into account how many tasks have been assigned to each worker
 * and use the one that is currently handling the minimum number of tasks.
 *
 */
final class PickupLeastJobs         extends WorkerStrategyAbstract
                                    implements PickupStrategyInterface
{
    public function __construct() {}
    
    public function pickupWorker(WorkerTypeEnum $workerType = null, array $possibleWorkers = null): ?WorkerDescriptor
    {
        $foundWorker                = null;
        $bestJobCount               = 0;
        
        foreach ($this->getWorkerPool()?->getWorkers() ?? [] as $worker) {
            if ($workerType !== null && $worker->type !== $workerType->value) {
                continue;
            }
            
            if ($possibleWorkers !== null && false === in_array($worker->id, $possibleWorkers)) {
                continue;
            }
            
            if(false === $worker->getWorker()->isReady()) {
                continue;
            }
            
            if($bestJobCount === 0) {
                $bestJobCount       = $worker->getWorker()->getJobsCount();
                $foundWorker        = $worker;
            } elseif ($worker->getWorker()->getJobsCount() < $bestJobCount) {
                $bestJobCount       = $worker->getWorker()->getJobsCount();
                $foundWorker        = $worker;
            }
            
            if($bestJobCount === 0) {
                return $foundWorker;
            }
        }
        
        return $foundWorker;
    }
}