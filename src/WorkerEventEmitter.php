<?php
declare(strict_types=1);

namespace CT\AmpPool;

final class WorkerEventEmitter implements WorkerEventEmitterInterface
{
    private array $listeners = [];
    
    public function addWorkerEventListener(\Closure $listener): void
    {
        $this->listeners[] = $listener;
    }
    
    public function removeWorkerEventListener(\Closure $listener): void
    {
        foreach ($this->listeners as $key => $value) {
            if ($value === $listener) {
                unset($this->listeners[$key]);
            }
        }
    }
    
    public function emitWorkerEvent(mixed $event): void
    {
        foreach ($this->listeners as $listener) {
            $listener($event);
        }
    }
}