<?php
declare(strict_types=1);

namespace CT\AmpPool\Coroutine;

use Amp\Cancellation;
use Amp\DeferredFuture;
use Amp\Future;
use CT\AmpPool\Coroutine\Exceptions\CoroutineNotStarted;
use CT\AmpPool\Coroutine\Exceptions\CoroutineTerminationException;
use Revolt\EventLoop;
use Revolt\EventLoop\Suspension;

final class Coroutine
{
    /**
     * @var array<Coroutine>
     */
    private static array $coroutines = [];
    private static array $coroutinesQueue = [];
    private static int $highestPriority = 0;
    private static ?Suspension $managerSuspension = null;
    private static string $managerCallbackId = '';
    private static bool $isRunning = true;
    private static ?DeferredFuture $future = null;
    private static \Throwable|null $stopException = null;
    private static bool $managerResumed = false;
    
    private static function init(): void
    {
        if (self::$managerCallbackId !== '') {
            return;
        }
        
        self::$future               = new DeferredFuture();
        self::$stopException        = null;
        self::$isRunning            = true;
        self::$managerCallbackId    = EventLoop::defer(self::manageCoroutines(...));
    }
    
    private static function manageCoroutines(): void
    {
        self::$managerSuspension    = EventLoop::getSuspension();
        
        while (self::$coroutines !== [] && self::$isRunning) {
            
            self::$managerResumed   = false;
            
            if(self::$coroutinesQueue === []) {
                
                self::$highestPriority = 0;
                
                foreach (self::$coroutines as $coroutine) {
                    if($coroutine->getPriority() > self::$highestPriority) {
                        self::$highestPriority = $coroutine->getPriority();
                    }
                }
                
                foreach (self::$coroutines as $coroutine) {
                    if($coroutine->getPriority() === self::$highestPriority) {
                        self::$coroutinesQueue[] = $coroutine;
                    }
                }
            }
            
            $coroutine              = array_shift(self::$coroutinesQueue);
            $coroutine->suspension?->resume();
            self::$managerSuspension->suspend();
        }
        
        try {
            
            if(self::$stopException !== null) {
                foreach (self::$coroutines as $callbackId => $coroutine) {
                    
                    if($coroutine->suspension === null) {
                        EventLoop::cancel($callbackId);
                    } else {
                        $coroutine->suspension->throw(self::$stopException);
                    }
                }
            }
            
        } finally {
            
            $future                     = self::$future;
            
            self::$stopException        = null;
            self::$future               = null;
            self::$managerCallbackId    = '';
            self::$coroutinesQueue      = [];
            self::$coroutines           = [];
            self::$stopException        = null;
            self::$future               = null;
            self::$managerCallbackId    = '';
            
            $future->complete(self::$stopException);
        }
    }
    
    public static function run(\Closure $closure, int $priority = 0): Future
    {
        self::init();
        
        $coroutine                  = new self($priority);
        
        $callbackId                 = EventLoop::defer(static function (string $callbackId)
                                    use ($closure, $coroutine, $priority): void {
            
            $suspension             = EventLoop::getSuspension();
            
            if(false === array_key_exists($callbackId, self::$coroutines)) {
                
                if(false === $coroutine->coroutineFuture->isComplete()) {
                    $coroutine->coroutineFuture->error(new CoroutineNotStarted);
                }
                
                return;
            }
            
            $coroutine->suspension = $suspension;
            
            try {
                $result             = $closure($coroutine);
                
                if(false === $coroutine->coroutineFuture->isComplete()) {
                    $coroutine->coroutineFuture->complete($result);
                }
            } catch (\Throwable $exception) {
                
                if(false === $coroutine->coroutineFuture->isComplete()) {
                    $coroutine->coroutineFuture->error($exception);
                }
                
                if($exception !== self::$stopException) {
                    throw $exception;
                }
                
            } finally {
                if(false === $coroutine->coroutineFuture->isComplete()) {
                    $coroutine->coroutineFuture->complete();
                }
                
                unset(self::$coroutines[$callbackId]);
                self::managerResume();
            }
        });
        
        self::$coroutines[$callbackId] = $coroutine;
        
        if($priority >= self::$highestPriority) {
            self::$coroutinesQueue  = [];
        }
        
        return $coroutine->getFuture();
    }
    
    protected static function managerResume(): void
    {
        if(self::$managerResumed) {
            return;
        }
        
        self::$managerResumed       = true;
        self::$managerSuspension?->resume();
    }
    
    public static function awaitAll(Cancellation $cancellation = null): void
    {
        if(self::$coroutines === [] || self::$future === null) {
            return;
        }
        
        self::$future->getFuture()->await($cancellation);
    }
    
    public static function stopAll(\Throwable $exception = null): void
    {
        $exception                  ??= new CoroutineTerminationException();
        self::$isRunning            = false;
        self::$stopException        = $exception;
        self::managerResume();
    }
    
    private DeferredFuture $coroutineFuture;
    
    public function __construct(
        private readonly int $priority,
        private Suspension|null $suspension = null,
        private int $startAt = 0,
        private readonly int $timeLimit = 0
    )
    {
        $this->coroutineFuture      = new DeferredFuture;
        
        if($this->startAt === 0) {
            $this->startAt          = time();
        }
    }
    
    public function getFuture(): Future
    {
        return $this->coroutineFuture->getFuture();
    }
    
    public function suspend(): void
    {
        self::$managerSuspension?->resume();
        $this->suspension->suspend();
    }
    
    public function getPriority(): int
    {
        return $this->priority;
    }
    
    public function getStartAt(): int
    {
        return $this->startAt;
    }
    
    public function getTimeLimit(): int
    {
        return $this->timeLimit;
    }
    
    public function getCoroutinesCount(): int
    {
        return count(self::$coroutines);
    }
}