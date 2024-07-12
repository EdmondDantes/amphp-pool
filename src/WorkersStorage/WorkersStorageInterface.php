<?php
declare(strict_types=1);

namespace IfCastle\AmpPool\WorkersStorage;

interface WorkersStorageInterface
{
    public static function instanciate(int $workersCount = 0): static;

    public function getWorkerState(int $workerId): WorkerStateInterface;

    public function reviewWorkerState(int $workerId): WorkerStateInterface;

    /**
     * @return WorkerStateInterface[]
     */
    public function foreachWorkers(): array;

    public function readWorkerState(int $workerId, int $offset = 0): string;

    public function updateWorkerState(int $workerId, string $data, int $offset = 0): void;

    public function getApplicationState(): ApplicationStateInterface;

    public function readApplicationState(): string;

    public function updateApplicationState(string $data): void;

    public function getMemoryUsage(): MemoryUsageInterface;

    public function readMemoryUsage(): string;

    public function updateMemoryUsage(string $data): void;

    public function close(): void;
}
