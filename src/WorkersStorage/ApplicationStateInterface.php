<?php
declare(strict_types=1);

namespace CT\AmpPool\WorkersStorage;

interface ApplicationStateInterface
{
    public static function instanciate(WorkersStorageInterface $workersStorage, int $workersCount): static;
    public function getStructureSize(): int;

    public function getWorkersCount(): int;

    public function getUptime(): int;

    public function getStartedAt(): int;

    public function getLastRestartedAt(): int;

    public function getRestartsCount(): int;

    public function getWorkersErrors(): int;
    public function getMemoryFree(): int;
    public function getMemoryTotal(): int;
    public function getLoadAverage(): float;

    public function update(): void;
    public function read(): void;
}
