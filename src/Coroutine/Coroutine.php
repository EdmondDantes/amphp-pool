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
    
    private static function init(): void
    {
        if (self::$managerCallbackId !== '') {
            return;
        }
        
        self::$future               = new DeferredFuture();
        self::$managerCallbackId    = EventLoop::defer(self::manageCoroutines(...));
    }
    
    private static function manageCoroutines(): void
    {
        self::$managerSuspension    = EventLoop::getSuspension();
        
        while (self::$coroutines !== [] && self::$isRunning) {
            
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
        
        self::$future->complete();
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
            } finally {
                unset(self::$coroutines[$callbackId]);
                self::$managerSuspension?->resume();
            }
        });
        
        self::$coroutines[$callbackId] = new self($priority);
        
        if($priority >= self::$highestPriority) {
            self::$coroutinesQueue  = [];
        }
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
        self::$managerSuspension?->resume();
    }
    
    public static function stopAllWithException(\Throwable $exception): void
    {
        self::$isRunning            = false;
        
        foreach (self::$coroutines as $descriptor) {
            $descriptor->suspension->throw($exception);
        }
        
        self::$managerSuspension?->resume();
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