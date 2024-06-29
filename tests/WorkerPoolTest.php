<?php
declare(strict_types=1);

namespace CT\AmpPool;

use Amp\Parallel\Context\ContextPanicError;
use Amp\Sync\ChannelException;
use CT\AmpPool\Exceptions\FatalWorkerException;
use CT\AmpPool\PoolState\PoolStateStorage;
use CT\AmpPool\Strategies\RestartStrategy\RestartNever;
use CT\AmpPool\WorkerPoolMocks\EntryPointHello;
use CT\AmpPool\WorkerPoolMocks\EntryPointWait;
use CT\AmpPool\WorkerPoolMocks\FatalWorkerEntryPoint;
use CT\AmpPool\WorkerPoolMocks\RestartEntryPoint;
use CT\AmpPool\WorkerPoolMocks\RestartStrategies\RestartNeverWithLastError;
use CT\AmpPool\WorkerPoolMocks\RestartStrategies\RestartTwice;
use CT\AmpPool\WorkerPoolMocks\Runners\RunnerLostChannel;
use CT\AmpPool\WorkerPoolMocks\StartCounterEntryPoint;
use CT\AmpPool\WorkerPoolMocks\TerminateWorkerEntryPoint;
use CT\AmpPool\WorkerPoolMocks\TestEntryPointWaitTermination;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Revolt\EventLoop;

class WorkerPoolTest extends TestCase
{
    #[RunInSeparateProcess]
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

    #[RunInSeparateProcess]
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

        EventLoop::delay(0.2, fn () => $workerPool->stop());

        $workerPool->run();

        $this->assertFileExists(TestEntryPointWaitTermination::getFile());
    }

    #[RunInSeparateProcess]
    public function testAwaitStart(): void
    {
        $workerPool                 = new WorkerPool;
        $workerPool->describeGroup(new WorkerGroup(
            TestEntryPointWaitTermination::class,
            WorkerTypeEnum::SERVICE,
            minWorkers: 2,
            restartStrategy: new RestartNever
        ));

        $awaitDone                  = false;

        EventLoop::queue(function () use ($workerPool, &$awaitDone) {

            $workerPool->awaitStart();
            $awaitDone              = true;
            $workerPool->stop();
        });

        $workerPool->run();

        $this->assertTrue($awaitDone, 'Await start should be done');
    }

    #[RunInSeparateProcess]
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

        EventLoop::delay(0.2, fn () => $workerPool->restart());

        $workerPool->run();

        $this->assertEquals(0, $restartStrategy->restarts);
        $this->assertFileExists(RestartEntryPoint::getFile());
        $this->assertEquals(1, (int) \file_get_contents(RestartEntryPoint::getFile()));
    }

    #[RunInSeparateProcess]
    public function testStartWithMinZero(): void
    {
        EntryPointHello::removeFile();

        $workerPool                 = new WorkerPool;
        $workerPool->describeGroup(new WorkerGroup(
            EntryPointHello::class,
            WorkerTypeEnum::SERVICE,
            maxWorkers:                1,
            restartStrategy:           new RestartNever
        ));

        $workerPool->run();

        $this->assertFileDoesNotExist(EntryPointHello::getFile());

        EntryPointHello::removeFile();
    }

    #[RunInSeparateProcess]
    public function testStartWithMaxZero(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Max workers must be a positive integer');

        $workerPool                 = new WorkerPool;
        $workerPool->describeGroup(new WorkerGroup(
            EntryPointHello::class,
            WorkerTypeEnum::SERVICE,
            maxWorkers:                0,
            restartStrategy:           new RestartNever
        ));

        $workerPool->run();
    }

    #[RunInSeparateProcess]
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

    #[RunInSeparateProcess]
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
     * Check if pool state is updated after worker started.
     *
     * @throws \Throwable
     */
    #[RunInSeparateProcess]
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

    #[RunInSeparateProcess]
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

    #[RunInSeparateProcess]
    public function testScale(): void
    {
        StartCounterEntryPoint::removeFile();

        $workerPool                 = new WorkerPool;
        $workerPool->describeGroup(new WorkerGroup(
            StartCounterEntryPoint::class,
            WorkerTypeEnum::SERVICE,
            minWorkers:                1,
            maxWorkers:                5,
            restartStrategy:           new RestartNever
        ));

        EventLoop::delay(1, function () use ($workerPool) {
            // Scale workers to 3 (1 + 2)
            $workerPool->scaleWorkers(1, 2);
            $workerPool->awaitStart();
            $workerPool->stop();
        });

        $workerPool->run();

        $this->assertFileExists(StartCounterEntryPoint::getFile());
        $this->assertEquals(3, (int) \file_get_contents(StartCounterEntryPoint::getFile()));

        StartCounterEntryPoint::removeFile();
    }

}
