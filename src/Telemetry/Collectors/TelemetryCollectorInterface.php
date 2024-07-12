<?php
declare(strict_types=1);

namespace IfCastle\AmpPool\Telemetry\Collectors;

interface TelemetryCollectorInterface
{
    public function flushTelemetry(): void;
}
