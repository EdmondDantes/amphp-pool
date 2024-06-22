<?php
declare(strict_types=1);

namespace CT\AmpPool\WorkerPoolMocks\RestartStrategies;

use CT\AmpPool\Strategies\RestartStrategy\RestartStrategyInterface;

final class RestartNeverWithLastError implements RestartStrategyInterface
{
    public mixed $lastError = null;
    
    public function shouldRestart(mixed $exitResult): int
    {
        $this->lastError            = $exitResult;
        return -1;
    }
    
    public function getFailReason(): string
    {
        return 'Never restart';
    }
}