<?php
declare(strict_types=1);

namespace IfCastle\AmpPool\Telemetry\Collectors;

use IfCastle\AmpPool\WorkersStorage\WorkersStorageInterface;

interface ApplicationCollectorInterface extends TelemetryCollectorInterface
{
    public static function instanciate(WorkersStorageInterface $workersStorage): self;

    public function startApplication(): void;
    public function restartApplication(): void;
    public function updateApplicationState(array $workersPid): void;
    public function stopApplication(): void;
}
