<?php
declare(strict_types=1);

namespace CT\AmpPool\EntryPoints\RestartStrategies;

use CT\AmpPool\Strategies\RestartStrategy\RestartStrategyInterface;
use CT\AmpPool\WorkerGroupInterface;

final class RestartTwice implements RestartStrategyInterface
{
    public int $restarts = 0;
    
    public function shouldRestart(mixed $exitResult): int
    {
        if($this->restarts >= 2) {
            return -1;
        }
        
        $this->restarts++;
        return 0;
    }
    
    public function getFailReason(): string
    {
        return 'Restarted twice';
    }
    
    public function onWorkerStart(int $workerId, WorkerGroupInterface $group): void
    {
    }
}