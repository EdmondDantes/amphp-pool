<?php
declare(strict_types=1);

namespace IfCastle\AmpPool\Telemetry\Collectors;

interface JobCollectorInterface extends TelemetryCollectorInterface
{
    public function jobAccepted(): void;

    public function jobProcessed(): void;

    public function jobError(): void;

    public function jobRejected(): void;

    public function jobProcessing(): void;

    public function jobUnProcessing(bool $withError = false): void;
}
