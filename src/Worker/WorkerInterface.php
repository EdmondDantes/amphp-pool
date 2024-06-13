<?php
declare(strict_types=1);

namespace CT\AmpServer\Worker;

use CT\AmpServer\WorkerGroup;
use Psr\Log\LoggerInterface;

interface WorkerInterface
{
    /**
     * @return array<int, WorkerGroup>
     */
    public function getGroupsScheme(): array;
    public function getWorkerId(): int;
    public function getWorkerGroupId(): int;
    public function getWorkerType(): string;
    public function getLogger(): LoggerInterface;
}