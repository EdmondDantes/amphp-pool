<?php
declare(strict_types=1);

namespace IfCastle\AmpPool\JobIpc;

interface JobRequestInterface
{
    public function getJobId(): int;
    public function getFromWorkerId(): int;
    public function getWorkerGroupId(): int;
    public function getPriority(): int;
    public function getWeight(): int;
    public function getData(): string;
}
