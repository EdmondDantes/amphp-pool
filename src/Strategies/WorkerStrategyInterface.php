<?php
declare(strict_types=1);

namespace IfCastle\AmpPool\Strategies;

use IfCastle\AmpPool\Worker\WorkerInterface;
use IfCastle\AmpPool\WorkerGroupInterface;
use IfCastle\AmpPool\WorkerPoolInterface;

interface WorkerStrategyInterface
{
    public function getWorkerPool(): WorkerPoolInterface|null;
    public function getWorker(): WorkerInterface|null;

    public function getSelfWorker(): WorkerInterface|null;

    public function getWorkerGroup(): WorkerGroupInterface|null;

    public function setWorkerPool(WorkerPoolInterface $workerPool): self;

    public function setWorker(WorkerInterface $worker, bool $isSelfWorker): self;

    public function setWorkerGroup(WorkerGroupInterface $workerGroup): self;

    public function onStarted(): void;
    public function onStopped(): void;
}
