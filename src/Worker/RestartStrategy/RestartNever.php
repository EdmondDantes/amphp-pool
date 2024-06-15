<?php
declare(strict_types=1);

namespace CT\AmpPool\Worker\RestartStrategy;

use CT\AmpPool\WorkerPoolInterface;

final class RestartNever implements RestartStrategyInterface
{
    public function __construct(private readonly WorkerPoolInterface $workerPool) {}
    
    public function shouldRestart(mixed $exitResult): int
    {
        // TODO: Implement shouldRestart() method.
    }
    
    public function getFailReason(): string
    {
        // TODO: Implement getFailReason() method.
    }
}