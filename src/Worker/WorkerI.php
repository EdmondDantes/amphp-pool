<?php
declare(strict_types=1);

namespace CT\AmpServer\Worker;

use Psr\Log\LoggerInterface;

interface WorkerI
{
    public function getWorkerId(): int;
    public function getWorkerGroupId(): int;
    public function getWorkerType(): string;
    public function getLogger(): LoggerInterface;
}