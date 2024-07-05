<?php
declare(strict_types=1);

namespace CT\AmpPool\WorkersStorage;

interface MemoryUsageInterface
{
    public static function instanciate(WorkersStorageInterface $workersStorage, int $workersCount = 0): static;
    public function getStructureSize(): int;
    public function getWorkersMemoryUsageStat(): array;
    public function getWorkersMemoryUsage(int $workerId): int;

    public function update(): void;
    public function read(): void;
}
