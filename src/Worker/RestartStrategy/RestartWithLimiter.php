<?php
declare(strict_types=1);

namespace CT\AmpPool\Worker\RestartStrategy;

use CT\AmpPool\WorkerGroupInterface;

class RestartWithLimiter            extends WorkerStrategyAbstract
                                    implements RestartStrategyInterface
{
    public function __construct(
        private int $maxRestarts = 3,
        private int $restartInterval = 60,
        private int $progressInterval = 10,
        private int $progressThreshold = 10,
    ) {}
    
    
    public function shouldRestart(mixed $exitResult): int
    {
        // TODO: Implement shouldRestart() method.
    }
    
    public function getFailReason(): string
    {
        // TODO: Implement getFailReason() method.
    }
    
    public function onWorkerStart(int $workerId, WorkerGroupInterface $group): void
    {
        // TODO: Implement onWorkerStart() method.
    }
}