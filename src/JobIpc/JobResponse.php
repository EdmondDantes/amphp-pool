<?php
declare(strict_types=1);

namespace IfCastle\AmpPool\JobIpc;

use IfCastle\AmpPool\Exceptions\RemoteException;

final readonly class JobResponse implements JobResponseInterface
{
    public function __construct(
        private int    $jobId,
        private int    $fromWorkerId,
        private int    $workerGroupId,
        private int    $errorCode,
        private string $data,
        private ?RemoteException $exception = null
    ) {
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

    public function getErrorCode(): int
    {
        return $this->errorCode;
    }

    public function getData(): string
    {
        return $this->data;
    }

    public function getException(): ?RemoteException
    {
        return $this->exception;
    }
}
