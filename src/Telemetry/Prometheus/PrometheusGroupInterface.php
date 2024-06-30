<?php
declare(strict_types=1);

namespace CT\AmpPool\Telemetry\Prometheus;

interface PrometheusGroupInterface
{
    public function getPrometheusAddress(): string;
}