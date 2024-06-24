<?php
declare(strict_types=1);

namespace CT\AmpPool\Integration\WorkerIpc;

use Amp\TimeoutCancellation;
use CT\AmpPool\Strategies\RestartStrategy\RestartNever;
use CT\AmpPool\WorkerGroup;
use CT\AmpPool\WorkerPool;
use CT\AmpPool\WorkerTypeEnum;
use PHPUnit\Framework\TestCase;

class WorkerToWorkerTest            extends TestCase
{
    public function testWorkerToWorkerMessage(): void
    {
        $workerPool                 = new WorkerPool();
        
        $workerPool->describeGroup(new WorkerGroup(
                                       entryPointClass: WorkerTestEntryPoint::class,
                                       workerType     : WorkerTypeEnum::JOB,
                                       minWorkers     : 1,
                                       groupName      : EntryPoint::GROUP1,
                                       restartStrategy: new RestartNever
                                   ));
        
        $workerPool->describeGroup(new WorkerGroup(
                                       entryPointClass: EntryPoint::class,
                                       workerType     : WorkerTypeEnum::REACTOR,
                                       minWorkers     : 1,
                                       groupName      : EntryPoint::GROUP2,
                                       restartStrategy: new RestartNever
        ));
        
        $workerPool->run();
        $workerPool->awaitTermination(new TimeoutCancellation(5));
    }
}