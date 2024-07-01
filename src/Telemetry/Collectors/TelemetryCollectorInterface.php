<?php
declare(strict_types=1);

namespace CT\AmpPool\Telemetry\Collectors;

interface TelemetryCollectorInterface
{
    public function flushTelemetry(): void;
}
