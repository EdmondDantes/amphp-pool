<?php
declare(strict_types=1);

namespace CT\AmpPool\Worker\WorkerState;

interface WorkerStateStorageInterface extends WorkerStateStorageReadableInterface
{
    public function workerReady(): void;
    public function workerBusy(): void;
    public function incrementJobCount(): void;
    public function decrementJobCount(): void;
}