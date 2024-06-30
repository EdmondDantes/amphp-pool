<?php
declare(strict_types=1);

namespace CT\AmpPool\Telemetry\Prometheus;

use CT\AmpPool\WorkerGroup;
use CT\AmpPool\WorkerTypeEnum;

final class PrometheusGroup         extends WorkerGroup
{
    public function __construct()
    {
        parent::__construct(
            PrometheusService::class,
            WorkerTypeEnum::SERVICE,
            1,
            1,
            'Prometheus'
        );
    }
}