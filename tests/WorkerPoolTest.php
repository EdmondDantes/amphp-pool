<?php
declare(strict_types=1);

namespace CT\AmpPool;

use Amp\Parallel\Context\ContextException;
use Amp\TimeoutCancellation;
use CT\AmpPool\Exceptions\FatalWorkerException;
use CT\AmpPool\WorkerPoolMocks\FatalWorkerEntryPoint;
use CT\AmpPool\WorkerPoolMocks\RestartStrategies\RestartNeverWithLastError;
use CT\AmpPool\WorkerPoolMocks\RestartStrategies\RestartTwice;
use CT\AmpPool\WorkerPoolMocks\Runners\RunnerLostChannel;
use CT\AmpPool\WorkerPoolMocks\TestEntryPoint;
use CT\AmpPool\WorkerPoolMocks\TestEntryPointWaitTermination;
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
        $workerPool->awaitTermination(new TimeoutCancellation(5));
        
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

        $exception                  = null;

        try {
            $workerPool->awaitTermination();
        } catch (\Throwable $exception) {
        }

        $this->assertInstanceOf(FatalWorkerException::class, $exception);
        $this->assertEquals(0, $restartStrategy->restarts, 'Worker should not be restarted');
    }
    
    public function testChannelLost(): void
    {
        $restartStrategy            = new RestartNeverWithLastError;
        
        $workerPool                 = new WorkerPool;
        
        $workerPool->describeGroup(new WorkerGroup(
            TestEntryPoint::class,
            WorkerTypeEnum::JOB,
            minWorkers     : 1,
            runnerStrategy : new RunnerLostChannel,
            restartStrategy: $restartStrategy
        ));

        $exception                  = null;

        try {
            $workerPool->run();
        } catch (\Throwable $exception) {
        }

        if($exception !== null) {
            $this->assertInstanceOf(FatalWorkerException::class, $exception);
        }

        $workerPool->awaitTermination();

        if($exception === null) {
            $this->assertInstanceOf(FatalWorkerException::class, $restartStrategy->lastError);
        }
    }
}
