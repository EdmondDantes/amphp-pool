<?php
declare(strict_types=1);

namespace CT\AmpPool;

use CT\AmpPool\Strategies\RestartStrategy\RestartNever;
use PHPUnit\Framework\TestCase;

class WorkerPoolTest                extends TestCase
{
    public function testStart(): void
    {
        TestEntryPoint::removeFile();
        
        $workerPool                 = new WorkerPool;
        $workerPool->describeGroup(new WorkerGroup(
            TestEntryPoint::class,
            WorkerTypeEnum::JOB,
            minWorkers: 1,
            restartStrategy: new RestartNever
        ));
        
        $workerPool->run();
        $workerPool->awaitTermination();
        
        $this->assertFileExists(TestEntryPoint::getFile());
        
        TestEntryPoint::removeFile();
    }
    
    public function testStop(): void
    {
        TestEntryPointWaitTermination::removeFile();
        
        $workerPool                 = new WorkerPool;
        $workerPool->describeGroup(new WorkerGroup(
            TestEntryPointWaitTermination::class,
            WorkerTypeEnum::JOB,
            minWorkers: 1,
            restartStrategy: new RestartNever
        ));
        
        $workerPool->run();
        $workerPool->stop();
        $workerPool->awaitTermination();
        
        $this->assertFileExists(TestEntryPointWaitTermination::getFile());
    }
    
    public function testRestart(): void
    {
        $restartStrategy            = new RestartTwice;
        
        $workerPool                 = new WorkerPool;
        $workerPool->describeGroup(new WorkerGroup(
            TestEntryPoint::class,
            WorkerTypeEnum::JOB,
            minWorkers: 1,
            restartStrategy: $restartStrategy
        ));
        
        $workerPool->run();
        $workerPool->awaitTermination();
        
        $this->assertEquals(2, $restartStrategy->restarts);
    }
    
    public function testFatalWorkerException(): void
    {
        $restartStrategy            = new RestartTwice;
        
        $workerPool                 = new WorkerPool;
        $workerPool->describeGroup(new WorkerGroup(
            FatalWorkerEntryPoint::class,
            WorkerTypeEnum::JOB,
            minWorkers: 1,
            restartStrategy: $restartStrategy
        ));
        
        $workerPool->run();
        $workerPool->awaitTermination();
        
        $this->assertEquals(0, $restartStrategy->restarts, 'Worker should not be restarted');
    }
}
