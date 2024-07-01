<?php
declare(strict_types=1);

namespace CT\AmpPool\Telemetry\Prometheus;

use CT\AmpPool\WorkerGroup;
use CT\AmpPool\WorkerTypeEnum;

final class PrometheusGroup extends WorkerGroup implements PrometheusGroupInterface
{
    public function __construct(private readonly string $address = '0.0.0.0:9091')
    {
        parent::__construct(
            PrometheusService::class,
            WorkerTypeEnum::SERVICE,
            1,
            1,
            'Prometheus'
        );
    }

    public function getPrometheusAddress(): string
    {
        return $this->address;
    }
}
