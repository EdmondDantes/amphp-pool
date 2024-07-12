<?php
declare(strict_types=1);

namespace IfCastle\AmpPool\JobIpc;

final readonly class JobRequest implements JobRequestInterface
{
    public function __construct(private int $jobId, private int $fromWorkerId, private int $workerGroupId, private int $priority, private int $weight, private string $data)
    {
    }

    public function getJobId(): int
    {
        return $this->jobId;
    }

    public function getFromWorkerId(): int
    {
        return $this->fromWorkerId;
    }

    public function getWorkerGroupId(): int
    {
        return $this->workerGroupId;
    }

    public function getPriority(): int
    {
        return $this->priority;
    }

    public function getWeight(): int
    {
        return $this->weight;
    }

    public function getData(): string
    {
        return $this->data;
    }
}
