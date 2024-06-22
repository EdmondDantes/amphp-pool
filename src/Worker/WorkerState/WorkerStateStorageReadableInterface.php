<?php
declare(strict_types=1);

namespace CT\AmpPool\Worker\WorkerState;

interface WorkerStateStorageReadableInterface
{
    public function getWorkerId(): int;
    
    public function update(): void;

    public function isWorkerReady(): bool;

    public function getJobCount(): int;

    public function getWorkerGroupId(): int;
}