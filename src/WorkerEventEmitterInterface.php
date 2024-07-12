<?php
declare(strict_types=1);

namespace IfCastle\AmpPool;

interface WorkerEventEmitterInterface
{
    public function addWorkerEventListener(callable $listener): void;
    public function removeWorkerEventListener(callable $listener): void;
    public function emitWorkerEvent(mixed $event, int $workerId = 0): void;
    public function free(): void;
}
