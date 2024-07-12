<?php
declare(strict_types=1);

namespace IfCastle\AmpPool\WorkersStorage;

interface ApplicationStateInterface
{
    public static function instanciate(WorkersStorageInterface $workersStorage, int $workersCount, bool $isReadOnly = true): static;
    public function getStructureSize(): int;

    public function getWorkersCount(): int;

    public function getPid(): int;

    public function getUptime(): int;

    public function getStartedAt(): int;

    public function getLastRestartedAt(): int;

    public function getRestartsCount(): int;

    public function getWorkersErrors(): int;
    public function getMemoryFree(): int;
    public function getMemoryTotal(): int;
    public function getLoadAverage(): float;

    // setters

    public function applyPid(): static;

    public function setStartedAt(int $startedAt): static;
    public function setLastRestartedAt(int $lastRestartedAt): static;
    public function setRestartsCount(int $restartsCount): static;
    public function setWorkersErrors(int $workersErrors): static;
    public function setMemoryFree(int $memoryFree): static;
    public function setMemoryTotal(int $memoryTotal): static;
    public function setLoadAverage(float $loadAverage): static;

    public function update(): void;
    public function read(): void;

    public function toArray(): array;
}
