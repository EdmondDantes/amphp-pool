<?php
declare(strict_types=1);

namespace IfCastle\AmpPool\JobIpc;

interface JobResponseInterface
{
    public function getJobId(): int;
    public function getFromWorkerId(): int;
    public function getWorkerGroupId(): int;
    public function getErrorCode(): int;
    public function getData(): string;
    public function getException(): ?\Throwable;
}
