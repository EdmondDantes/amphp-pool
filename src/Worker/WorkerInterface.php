<?php
declare(strict_types=1);

namespace CT\AmpCluster\Worker;

use CT\AmpCluster\WorkerGroup;
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