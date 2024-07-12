<?php
declare(strict_types=1);

namespace IfCastle\AmpPool\Telemetry\Collectors;

interface ConnectionCollectorInterface extends TelemetryCollectorInterface
{
    public function connectionAccepted(): void;

    public function connectionProcessing(): void;

    public function connectionUnProcessing(bool $withError = false): void;

    public function connectionProcessed(): void;

    public function connectionError(): void;
}
