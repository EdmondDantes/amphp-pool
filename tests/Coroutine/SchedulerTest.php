<?php
declare(strict_types=1);

namespace IfCastle\AmpPool\Coroutine;

use Amp\TimeoutCancellation;
use PHPUnit\Framework\TestCase;
use Revolt\EventLoop;
use function Amp\Future\awaitAll;

class SchedulerTest extends TestCase
{
    protected array $runLog;

    public function testOneJob(): void
    {
        $scheduler                  = new Scheduler;

        $scheduler->run(new Coroutine(function (CoroutineInterface $coroutine) {
            $this->runLog[] = 1;
            $coroutine->suspend();
            $this->runLog[] = 2;
            $coroutine->suspend();
            $this->runLog[] = 3;
        }));

        $scheduler->awaitAll(new TimeoutCancellation(5));

        $this->assertEquals([1, 2, 3], $this->runLog);
    }

    public function testTwoJobs(): void
    {
        $scheduler                  = new Scheduler;

        $future1                    = $scheduler->run(new Coroutine(function (CoroutineInterface $coroutine) {
            $this->runLog[] = 1;
            $coroutine->suspend();
            $this->runLog[] = 2;

            return 1;
        }));

        $future2                    = $scheduler->run(new Coroutine(function (CoroutineInterface $coroutine) {
            $this->runLog[] = 4;
            $coroutine->suspend();
            $this->runLog[] = 5;

            return 2;
        }));

        $this->assertEquals([[], [1, 2]], awaitAll([$future1, $future2], new TimeoutCancellation(5)));

        $scheduler->awaitAll(new TimeoutCancellation(5));

        $this->assertEquals([1, 4, 2, 5], $this->runLog);
    }

    public function testJobWithPriority(): void
    {
        $scheduler                  = new Scheduler;

        $scheduler->run(new Coroutine(function (CoroutineInterface $coroutine) {
            $this->runLog[] = 1;
            $coroutine->suspend();
            $this->runLog[] = 2;
        }, 2));

        $scheduler->run(new Coroutine(function (CoroutineInterface $coroutine) {
            $this->runLog[] = 4;
            $coroutine->suspend();
            $this->runLog[] = 5;
        }, 1));

        $scheduler->run(new Coroutine(function () {$this->runLog[] = 8;}));

        $scheduler->awaitAll(new TimeoutCancellation(5));

        $this->assertEquals([1, 2, 4, 5, 8], $this->runLog);
    }

    public function testJobWithPriorityAndDefer(): void
    {
        $scheduler                  = new Scheduler;

        $scheduler->run(new Coroutine(function (CoroutineInterface $coroutine) {
            $this->runLog[] = 1;
            $coroutine->suspend();
            $this->runLog[] = 2;
        }, 2));

        EventLoop::queue(function () use ($scheduler) {
            $scheduler->run(new Coroutine(function () {$this->runLog[] = 8;}));
        });

        $scheduler->run(new Coroutine(function (CoroutineInterface $coroutine) {
            $this->runLog[] = 4;
            $coroutine->suspend();
            $this->runLog[] = 5;
        }, 1));

        $scheduler->awaitAll(new TimeoutCancellation(5));

        $this->assertEquals([1, 2, 4, 5, 8], $this->runLog);
    }

    public function testStopAll(): void
    {
        $scheduler                  = new Scheduler;

        $scheduler->run(new Coroutine(function () {
            $this->runLog[] = 1;
        }));

        $scheduler->stopAll();

        $scheduler->awaitAll(new TimeoutCancellation(5));

        $this->assertEquals([], $this->runLog);
    }

    public function testStopAllWithException(): void
    {
        $scheduler                  = new Scheduler;

        $scheduler->run(new Coroutine(function () {
            $this->runLog[] = 1;
        }));

        $scheduler->stopAll(new \Exception('Stop all with exception'));

        $scheduler->awaitAll(new TimeoutCancellation(5));

        $this->assertEquals([], $this->runLog);
    }

    public function testStopAllWithExceptionUntilRunning(): void
    {
        $scheduler                  = new Scheduler;

        $scheduler->run(new Coroutine(function (Coroutine $coroutine) {
            $this->runLog[] = 1;

            try {
                $coroutine->suspend();
            } catch (\Exception $exception) {
                $this->runLog[] = $exception->getMessage();
            }

            $this->runLog[] = 2;
        }));

        $scheduler->run(new Coroutine(function () use ($scheduler) {
            $scheduler->stopAll(new \Exception('Stop all with exception'));
        }));

        $scheduler->awaitAll(new TimeoutCancellation(5));

        $this->assertEquals([1, 'Stop all with exception', 2], $this->runLog);
    }

    protected function setUp(): void
    {
        $this->runLog              = [];
    }
}
