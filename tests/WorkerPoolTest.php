<?php
declare(strict_types=1);

namespace CT\AmpPool;

use CT\AmpPool\Strategies\RestartStrategy\RestartNever;
use PHPUnit\Framework\TestCase;

class WorkerPoolTest                extends TestCase
{
    public function testStart(): void
    {
        $workerPool                 = new WorkerPool;
        $workerPool->describeGroup(new WorkerGroup(
            TestEntryPoint::class,
            WorkerTypeEnum::JOB,
            minWorkers: 1,
            restartStrategy: new RestartNever
        ));
        
        $workerPool->run();
        $workerPool->awaitTermination();
    }
}
