<?php
declare(strict_types=1);

namespace CT\AmpPool\JobIpc;

final readonly class JobRequest
{
    public function __construct(public int $jobId, public int $fromWorkerId, public int $workerGroupId, public int $priority, public string $data) {}
}