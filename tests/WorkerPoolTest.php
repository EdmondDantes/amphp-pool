<?php
declare(strict_types=1);

namespace CT\AmpPool;

use Amp\Parallel\Context\ContextPanicError;
use Amp\Sync\ChannelException;
use CT\AmpPool\Exceptions\FatalWorkerException;
use CT\AmpPool\WorkerPoolMocks\FatalWorkerEntryPoint;
use CT\AmpPool\WorkerPoolMocks\RestartEntryPoint;
use CT\AmpPool\WorkerPoolMocks\RestartStrategies\RestartNeverWithLastError;
use CT\AmpPool\WorkerPoolMocks\RestartStrategies\RestartTwice;
use CT\AmpPool\WorkerPoolMocks\Runners\RunnerLostChannel;
use CT\AmpPool\WorkerPoolMocks\TestEntryPoint;
use CT\AmpPool\WorkerPoolMocks\TestEntryPointWaitTermination;
use CT\AmpPool\Strategies\RestartStrategy\RestartNever;
use PHPUnit\Framework\TestCase;
use Revolt\EventLoop;

class WorkerPoolTest                extends TestCase
{
    public function testStart(): void
    {
        TestEntryPoint::removeFile();
        
        $workerPool                 = new WorkerPool;
        $workerPool->describeGroup(new WorkerGroup(
            TestEntryPoint::class,
            WorkerTypeEnum::SERVICE,
            minWorkers: 2,
            restartStrategy: new RestartNever
        ));
        
        $workerPool->run();
        
        $this->assertFileExists(TestEntryPoint::getFile());
        
        TestEntryPoint::removeFile();
    }
    
    public function testStop(): void
    {
        TestEntryPointWaitTermination::removeFile();
        
        $workerPool                 = new WorkerPool;
        $workerPool->describeGroup(new WorkerGroup(
            TestEntryPointWaitTermination::class,
            WorkerTypeEnum::SERVICE,
            minWorkers: 2,
            restartStrategy: new RestartNever
        ));
        
        EventLoop::delay(1, fn() => $workerPool->stop());
        
        $workerPool->run();
        
        $this->assertFileExists(TestEntryPointWaitTermination::getFile());
    }
    
    public function testRestart(): void
    {
        $restartStrategy            = new RestartTwice;
        RestartEntryPoint::removeFile();
        
        $workerPool                 = new WorkerPool;
        $workerPool->describeGroup(new WorkerGroup(
            RestartEntryPoint::class,
            WorkerTypeEnum::SERVICE,
            minWorkers: 1,
            restartStrategy: $restartStrategy
        ));
        
        EventLoop::delay(1, fn() => $workerPool->restart());
        
        $workerPool->run();
        
        $this->assertEquals(0, $restartStrategy->restarts);
        $this->assertFileExists(RestartEntryPoint::getFile());
        $this->assertEquals(1, (int) file_get_contents(RestartEntryPoint::getFile()));
    }
    
    public function testFatalWorkerException(): void
    {
        $restartStrategy            = new RestartTwice;
        
        $workerPool                 = new WorkerPool;
        $workerPool->describeGroup(new WorkerGroup(
            FatalWorkerEntryPoint::class,
            WorkerTypeEnum::SERVICE,
            minWorkers: 1,
            restartStrategy: $restartStrategy
        ));
        
        $exception                  = null;

        try {
            $workerPool->run();
        } catch (\Throwable $exception) {
        }

        $this->assertInstanceOf(ContextPanicError::class, $exception);
        $this->assertEquals(0, $restartStrategy->restarts, 'Worker should not be restarted');
    }
    
    public function testTerminateWorkerException(): void
    {
        $this->markTestIncomplete('Not implemented yet');
    }
    
    public function testChannelLost(): void
    {
        $restartStrategy            = new RestartNeverWithLastError;
        
        $workerPool                 = new WorkerPool;
        
        $workerPool->describeGroup(new WorkerGroup(
            TestEntryPoint::class,
            WorkerTypeEnum::SERVICE,
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

        if($exception === null) {
            $this->assertInstanceOf(ChannelException::class, $restartStrategy->lastError);
        }
    }
}
