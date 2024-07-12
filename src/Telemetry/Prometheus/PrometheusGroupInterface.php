<?php
declare(strict_types=1);

namespace IfCastle\AmpPool\Telemetry\Prometheus;

interface PrometheusGroupInterface
{
    public function getPrometheusAddress(): string;
}
