<?php
declare(strict_types=1);

namespace CT\AmpPool\Worker\WorkerState;

interface WorkerStateStorageInterface extends WorkerStateStorageReadableInterface
{
    public function workerReady(): void;
    public function workerNotReady(): void;
    public function incrementJobCount(): void;
    public function decrementJobCount(): void;
    
    public function jobEnqueued(int $weight, bool $canAcceptMoreJobs): void;
    public function jobDequeued(int $weight, bool $canAcceptMoreJobs): void;
}