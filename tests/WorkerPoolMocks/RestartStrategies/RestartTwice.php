<?php
declare(strict_types=1);

namespace CT\AmpPool\WorkerPoolMocks\RestartStrategies;

use CT\AmpPool\Strategies\RestartStrategy\RestartStrategyInterface;

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
}