<?php
declare(strict_types=1);

namespace CT\AmpPool\Worker\PickupStrategy;

class PickFirstAvailable        extends PickupStrategyAbstract
{
    public function pickupWorker(array $possibleGroups = [], array $possibleWorkers = []): ?int
    {
        $workersInfo                = $this->getWorkersInfo();
        
        foreach ($this->iterate($possibleGroups, $possibleWorkers) as $workerId) {
            if($workersInfo->getWorkerState($workerId)->isReady()) {
                return $workerId;
            }
        }
        
        return null;
    }
}