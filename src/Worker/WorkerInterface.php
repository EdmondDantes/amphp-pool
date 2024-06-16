<?php
declare(strict_types=1);

namespace CT\AmpPool\Worker;

use CT\AmpPool\PoolState\PoolStateReadableInterface;
use CT\AmpPool\Worker\WorkerState\WorkersInfoInterface;
use CT\AmpPool\Worker\WorkerState\WorkerStateStorageInterface;
use CT\AmpPool\WorkerGroup;
use CT\AmpPool\WorkerTypeEnum;
use Psr\Log\LoggerInterface;

interface WorkerInterface
{
    /**
     * @return array<int, WorkerGroup>
     */
    public function getGroupsScheme(): array;
    
    public function getPoolStateStorage(): PoolStateReadableInterface;
    public function getWorkerStateStorage(): WorkerStateStorageInterface;
    public function getWorkersInfo(): WorkersInfoInterface;
    
    public function getWorkerId(): int;
    public function getWorkerGroup(): WorkerGroup;
    public function getWorkerGroupId(): int;
    public function getWorkerType(): WorkerTypeEnum;
    
    public function getLogger(): LoggerInterface;
}