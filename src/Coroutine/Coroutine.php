<?php
declare(strict_types=1);

namespace CT\AmpPool\Coroutine;

use Revolt\EventLoop;
use Revolt\EventLoop\Suspension;

final class Coroutine
{
    private static array $coroutines = [];
    private static ?Suspension $managerSuspension = null;
    private static string $managerCallbackId = '';
    private static bool $isRunning = true;
    
    private static function init(): void
    {
        if (self::$managerCallbackId !== '') {
            return;
        }
        
        self::$managerCallbackId    = EventLoop::defer(self::manageCoroutines(...));
    }
    
    private static function manageCoroutines(): void
    {
        self::$managerSuspension    = EventLoop::getSuspension();
        
        while (self::$isRunning) {
            
            $heightPriority         = 0;
            $lowestPriority         = 0;
            
            // Find the highest priority if exists
            foreach (self::$coroutines as $coroutine) {
                if($coroutine->getPriority() > $heightPriority) {
                    $heightPriority = $coroutine->getPriority();
                }
                
                if($coroutine->getPriority() < $lowestPriority) {
                    $lowestPriority = $coroutine->getPriority();
                }
            }
            
            if($lowestPriority === $heightPriority) {
                // Resume all
                foreach (self::$coroutines as $descriptor) {
                    $descriptor->suspension->resume();
                }
                
                self::$managerSuspension->suspend();
            } else {
                // Resume only the highest priority
                foreach (self::$coroutines as $descriptor) {
                    if($descriptor->getPriority() === $heightPriority) {
                        $descriptor->suspension->resume();
                    }
                }
            }
        }
        
        self::$managerSuspension->suspend();
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