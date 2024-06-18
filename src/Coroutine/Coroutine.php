<?php
declare(strict_types=1);

namespace CT\AmpPool\Coroutine;

use Amp\Cancellation;
use Amp\DeferredFuture;
use Amp\Future;
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
        
        if(self::$stopException !== null) {
            foreach (self::$coroutines as $coroutine) {
                $coroutine->suspension?->throw(self::$stopException);
            }
        }
        
        self::$coroutinesQueue      = [];
        self::$coroutines           = [];
        self::$future->complete(self::$stopException);
        self::$stopException        = null;
        self::$future               = null;
        self::$managerCallbackId    = '';
    }
    
    public static function run(\Closure $coroutine, int $priority = 0): void
    {
        self::init();
        
        $callbackId = EventLoop::defer(static function (string $callbackId) use ($coroutine, $priority): void {
            $suspension             = EventLoop::getSuspension();
            
            if(false === array_key_exists($callbackId, self::$coroutines)) {
                return;
            }
            
            self::$coroutines[$callbackId]->suspension = $suspension;
            
            try {
                $coroutine(self::$coroutines[$callbackId]);
            } catch (\Throwable $exception) {
                
                if($exception !== self::$stopException) {
                    throw $exception;
                }
                
            } finally {
                unset(self::$coroutines[$callbackId]);
                self::managerResume();
            }
        });
        
        self::$coroutines[$callbackId] = new self($priority);
        
        if($priority >= self::$highestPriority) {
            self::$coroutinesQueue  = [];
        }
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
    
    public static function stopAll(): void
    {
        self::$isRunning            = false;
        
        if(self::$managerSuspension !== null) {
            self::managerResume();
        } else {
            self::$coroutinesQueue  = [];
            self::$coroutines       = [];
            self::$managerCallbackId = '';
        }
    }
    
    public static function stopAllWithException(\Throwable $exception): void
    {
        self::$isRunning            = false;
        self::$stopException        = $exception;
        self::managerResume();
    }
    
    public function __construct(private int $priority, private Suspension|null $suspension = null, private int $startAt = 0)
    {
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
}