<?php
declare(strict_types=1);

namespace CT\AmpPool\Strategies\PickupStrategy;

final class PickFirstAvailable      extends PickupStrategyAbstract
{
    public function pickupWorker(array $possibleGroups = [], array $possibleWorkers = [], array $ignoredWorkers = [], int $priority = 0, int $tryCount = 0): ?int
    {
        $workersInfo                = $this->getWorkersInfo();
        
        if($workersInfo === null) {
            return null;
        }
        
        foreach ($this->iterate($possibleGroups, $possibleWorkers, $ignoredWorkers) as $workerId) {
            if($workersInfo->getWorkerState($workerId)->isReady()) {
                return $workerId;
            }
        }
        
        return null;
    }
}