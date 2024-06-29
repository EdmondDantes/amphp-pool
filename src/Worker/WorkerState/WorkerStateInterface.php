<?php
declare(strict_types=1);

namespace CT\AmpPool\Worker\WorkerState;

interface WorkerStateInterface
{
    public function isReady(): bool;

    public function getJobCount(): int;

    public function getGroupId(): int;

    public function getWorkerWeight(): int;

    public function pack(): string;

    public static function unpack(string $data): self;
}
