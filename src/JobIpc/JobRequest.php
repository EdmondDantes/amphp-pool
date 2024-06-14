<?php
declare(strict_types=1);

namespace CT\AmpCluster\JobIpc;

final readonly class JobRequest
{
    public function __construct(public int $jobId, public int $fromWorkerId, public int $workerGroupId, public int $dataLength, public string $data) {}
}