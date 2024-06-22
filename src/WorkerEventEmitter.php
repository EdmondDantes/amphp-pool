<?php
declare(strict_types=1);

namespace CT\AmpPool;

/**
 * Event emitter for worker events with weak references handlers.
 */
final class WorkerEventEmitter implements WorkerEventEmitterInterface
{
    private array $listeners        = [];
    
    public function addWorkerEventListener(\Closure $listener): void
    {
        $this->listeners[]          = \WeakReference::create($listener);
    }
    
    public function removeWorkerEventListener(\Closure $listener): void
    {
        foreach ($this->listeners as $key => $value) {
            if ($value->get() === $listener) {
                unset($this->listeners[$key]);
            }
        }
    }
    
    public function emitWorkerEvent(mixed $event, int $workerId = 0): void
    {
        foreach ($this->listeners as $key => $listener) {
            $listener               = $listener->get();
            
            if ($listener === null) {
                unset($this->listeners[$key]);
                continue;
            }
            
            $listener($event, $workerId);
        }
    }
    
    public function free(): void
    {
        $this->listeners            = [];
    }
}