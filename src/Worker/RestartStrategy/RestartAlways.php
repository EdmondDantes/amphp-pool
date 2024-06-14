<?php
declare(strict_types=1);

namespace CT\AmpCluster\Worker\RestartStrategy;

use CT\AmpCluster\WorkerPoolInterface;

final class RestartAlways implements RestartStrategyInterface
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