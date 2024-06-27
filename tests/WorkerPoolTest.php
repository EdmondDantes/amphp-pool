<?php
declare(strict_types=1);

namespace CT\AmpPool;

use Amp\Parallel\Context\ContextPanicError;
use Amp\Sync\ChannelException;
use CT\AmpPool\Exceptions\FatalWorkerException;
use CT\AmpPool\PoolState\PoolStateStorage;
use CT\AmpPool\WorkerPoolMocks\EntryPointWait;
use CT\AmpPool\WorkerPoolMocks\FatalWorkerEntryPoint;
use CT\AmpPool\WorkerPoolMocks\RestartEntryPoint;
use CT\AmpPool\WorkerPoolMocks\RestartStrategies\RestartNeverWithLastError;
use CT\AmpPool\WorkerPoolMocks\RestartStrategies\RestartTwice;
use CT\AmpPool\WorkerPoolMocks\Runners\RunnerLostChannel;
use CT\AmpPool\WorkerPoolMocks\EntryPointHello;
use CT\AmpPool\WorkerPoolMocks\TerminateWorkerEntryPoint;
use CT\AmpPool\WorkerPoolMocks\TestEntryPointWaitTermination;
use CT\AmpPool\Strategies\RestartStrategy\RestartNever;
use PHPUnit\Framework\TestCase;
use Revolt\EventLoop;

class WorkerPoolTest                extends TestCase
{
    public function testStart(): void
    {
        EntryPointHello::removeFile();
        
        $workerPool                 = new WorkerPool;
        $workerPool->describeGroup(new WorkerGroup(
                                       EntryPointHello::class,
                                       WorkerTypeEnum::SERVICE,
            minWorkers:                2,
            restartStrategy:           new RestartNever
        ));
        
        $workerPool->run();
        
        $this->assertFileExists(EntryPointHello::getFile());
        
        EntryPointHello::removeFile();
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
        
        EventLoop::delay(0.2, fn() => $workerPool->stop());
        
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
        
        EventLoop::delay(0.2, fn() => $workerPool->restart());
        
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
        $workerPool                 = new WorkerPool;
        $workerPool->describeGroup(new WorkerGroup(
            TerminateWorkerEntryPoint::class,
            WorkerTypeEnum::SERVICE,
            minWorkers:      2,
            restartStrategy: new RestartNever
        ));
        
        $workerPool->run();
        $this->assertTrue(true, 'Workers should be terminated without any exception');
    }
    
    /**
     * Check if pool state is updated after worker started
     *
     * @return void
     * @throws \Throwable
     */
    public function testPoolState(): void
    {
        $workerPool                 = new WorkerPool;
        $workerPool->describeGroup(new WorkerGroup(
            EntryPointWait::class,
            WorkerTypeEnum::SERVICE,
            minWorkers:      2,
            restartStrategy: new RestartNever
        ));

        $workerPool->describeGroup(new WorkerGroup(
            EntryPointWait::class,
            WorkerTypeEnum::SERVICE,
            minWorkers:      3,
            restartStrategy: new RestartNever
        ));
        
        $groups                     = null;
        
        EventLoop::delay(0.2, function () use ($workerPool, &$groups) {
            $poolState              = new PoolStateStorage;
            $groups                 = $poolState->update()->getGroupsState();
            $workerPool->stop();
        });
        
        $workerPool->run();
        
        $this->assertEquals([1 => [1, 2], 2 => [3, 5]], $groups, 'The First group have worker id 1 and 2, the second group have worker id 3, 4 and 5');

        // Check pool state after workers stopped
        $groups                 = (new PoolStateStorage)->update()->getGroupsState();

        $this->assertEquals([1 => [0, 0], 2 => [0, 0]], $groups, 'Any group should not have workers');
    }
    
    public function testChannelLost(): void
    {
        $restartStrategy            = new RestartNeverWithLastError;
        
        $workerPool                 = new WorkerPool;
        
        $workerPool->describeGroup(new WorkerGroup(
           EntryPointHello::class,
           WorkerTypeEnum::SERVICE,
            minWorkers     :           1,
            runnerStrategy :           new RunnerLostChannel,
            restartStrategy:           $restartStrategy
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
