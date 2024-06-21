<?php
declare(strict_types=1);

namespace CT\AmpPool\Strategies\PickupStrategy;

final class PickFirstAvailable      extends PickupStrategyAbstract
{
    public function pickupWorker(array $possibleGroups = [], array $possibleWorkers = [], array $ignoredWorkers = []): ?int
    {
        $workersInfo                = $this->getWorkersInfo();
        
        foreach ($this->iterate($possibleGroups, $possibleWorkers, $ignoredWorkers) as $workerId) {
            if($workersInfo->getWorkerState($workerId)->isReady()) {
                return $workerId;
            }
        }
        
        return null;
    }
}