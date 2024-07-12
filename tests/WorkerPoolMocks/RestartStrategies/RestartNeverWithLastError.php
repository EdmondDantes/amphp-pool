<?php
declare(strict_types=1);

namespace IfCastle\AmpPool\WorkerPoolMocks\RestartStrategies;

use IfCastle\AmpPool\Strategies\RestartStrategy\RestartStrategyInterface;

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
