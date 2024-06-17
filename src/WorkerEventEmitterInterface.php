<?php
declare(strict_types=1);

namespace CT\AmpPool;

interface WorkerEventEmitterInterface
{
    public function addWorkerEventListener(\Closure $listener): void;
    public function removeWorkerEventListener(\Closure $listener): void;
    public function emitWorkerEvent(mixed $event): void;
    public function free(): void;
}