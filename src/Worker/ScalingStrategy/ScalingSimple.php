<?php
declare(strict_types=1);

namespace CT\AmpCluster\Worker\ScalingStrategy;

use CT\AmpCluster\WorkerPoolInterface;

final class ScalingSimple implements ScalingStrategyInterface
{
    public function __construct(private readonly WorkerPoolInterface $workerPool) {}
    
    public function adjustWorkerCount(): int
    {
        // TODO: Implement adjustWorkerCount() method.
    }
}