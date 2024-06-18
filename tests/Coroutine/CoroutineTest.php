<?php
declare(strict_types=1);

namespace CT\AmpPool\Coroutine;

use Amp\TimeoutCancellation;
use PHPUnit\Framework\TestCase;

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
            $coroutine->suspend();
            $this->runLog[] = 3;
        });
        
        Coroutine::run(function (Coroutine $coroutine) {
            $this->runLog[] = 4;
            $coroutine->suspend();
            $this->runLog[] = 5;
            $coroutine->suspend();
            $this->runLog[] = 6;
        });
        
        Coroutine::awaitAll(new TimeoutCancellation(5));
        
        $this->assertEquals([1, 2, 3, 4, 5, 6], $this->runLog);
    }
    
    protected function setUp(): void
    {
        $this->runLog              = [];
    }
}
