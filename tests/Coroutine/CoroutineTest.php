<?php
declare(strict_types=1);

namespace CT\AmpPool\Coroutine;

use Amp\TimeoutCancellation;
use PHPUnit\Framework\TestCase;
use Revolt\EventLoop;

class CoroutineTest                 extends TestCase
{
    protected array $runLog;
    
    public function testOneJob(): void
    {
        Coroutine::run(function (Coroutine $coroutine) {
            $this->runLog[] = 1;
            $coroutine->suspend();
            $this->runLog[] = 2;
            $coroutine->suspend();
            $this->runLog[] = 3;
        });
        
        Coroutine::awaitAll(new TimeoutCancellation(5));
        
        $this->assertEquals([1, 2, 3], $this->runLog);
    }
    
    public function testTwoJobs(): void
    {
        Coroutine::run(function (Coroutine $coroutine) {
            $this->runLog[] = 1;
            $coroutine->suspend();
            $this->runLog[] = 2;
        });
        
        Coroutine::run(function (Coroutine $coroutine) {
            $this->runLog[] = 4;
            $coroutine->suspend();
            $this->runLog[] = 5;
        });
        
        Coroutine::awaitAll(new TimeoutCancellation(5));
        
        $this->assertEquals([1, 4, 2, 5], $this->runLog);
    }
    
    public function testJobWithPriority(): void
    {
        Coroutine::run(function (Coroutine $coroutine) {
            $this->runLog[] = 1;
            $coroutine->suspend();
            $this->runLog[] = 2;
        }, 2);
        
        Coroutine::run(function (Coroutine $coroutine) {
            $this->runLog[] = 4;
            $coroutine->suspend();
            $this->runLog[] = 5;
        }, 1);
        
        Coroutine::run(function () {$this->runLog[] = 8;});
        
        Coroutine::awaitAll(new TimeoutCancellation(5));
        
        $this->assertEquals([1, 2, 4, 5, 8], $this->runLog);
    }
    
    public function testJobWithPriorityAndDefer(): void
    {
        EventLoop::queue(function () {
            Coroutine::run(function () {$this->runLog[] = 8;});
        });
        
        Coroutine::run(function (Coroutine $coroutine) {
            $this->runLog[] = 1;
            $coroutine->suspend();
            $this->runLog[] = 2;
        }, 2);
        
        Coroutine::run(function (Coroutine $coroutine) {
            $this->runLog[] = 4;
            $coroutine->suspend();
            $this->runLog[] = 5;
        }, 1);
        
        Coroutine::awaitAll(new TimeoutCancellation(5));
        
        $this->assertEquals([1, 2, 4, 5, 8], $this->runLog);
    }
    
    public function testStopAll(): void
    {
        Coroutine::run(function () {
            $this->runLog[] = 1;
        });
        
        Coroutine::stopAll();
        
        Coroutine::awaitAll(new TimeoutCancellation(5));
        
        $this->assertEquals([], $this->runLog);
    }
    
    public function testStopAllWithException(): void
    {
        Coroutine::run(function () {
            $this->runLog[] = 1;
        });
        
        Coroutine::stopAllWithException(new \Exception());
        
        Coroutine::awaitAll(new TimeoutCancellation(5));
        
        $this->assertEquals([], $this->runLog);
    }
    
    public function testStopAllWithExceptionUntilRunning(): void
    {
        Coroutine::run(function (Coroutine $coroutine) {
            $this->runLog[] = 1;
            
            try {
                $coroutine->suspend();
            } catch (\Exception $exception) {
                $this->runLog[] = $exception->getMessage();
            }
            
            $this->runLog[] = 2;
        });
        
        Coroutine::run(function () {
            Coroutine::stopAllWithException(new \Exception('Stop all with exception'));
        });
        
        Coroutine::awaitAll(new TimeoutCancellation(5));
        
        $this->assertEquals([1, 'Stop all with exception', 2], $this->runLog);
    }
    
    protected function setUp(): void
    {
        $this->runLog              = [];
    }
}