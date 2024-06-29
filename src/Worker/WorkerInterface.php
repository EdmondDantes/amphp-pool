<?php
declare(strict_types=1);

namespace CT\AmpPool\Worker;

use Amp\Cancellation;
use Amp\Sync\Channel;
use CT\AmpPool\PoolState\PoolStateReadableInterface;
use CT\AmpPool\Worker\WorkerState\WorkersInfoInterface;
use CT\AmpPool\Worker\WorkerState\WorkerStateStorageInterface;
use CT\AmpPool\WorkerEventEmitterAwareInterface;
use CT\AmpPool\WorkerGroup;
use CT\AmpPool\WorkerTypeEnum;
use Psr\Log\LoggerInterface;

interface WorkerInterface extends WorkerEventEmitterAwareInterface
{
    /**
     * @return array<int, WorkerGroup>
     */
    public function getGroupsScheme(): array;

    public function sendMessageToWatcher(mixed $message): void;

    public function getWatcherChannel(): Channel;

    public function getPoolStateStorage(): PoolStateReadableInterface;
    public function getWorkerStateStorage(): WorkerStateStorageInterface;
    public function getWorkersInfo(): WorkersInfoInterface;

    public function getWorkerId(): int;
    public function getWorkerGroup(): WorkerGroup;
    public function getWorkerGroupId(): int;
    public function getWorkerType(): WorkerTypeEnum;

    public function getAbortCancellation(): Cancellation;

    public function mainLoop(): void;

    public function getLogger(): LoggerInterface;

    public function awaitTermination(?Cancellation $cancellation = null): void;

    public function stop(): void;

    public function isStopped(): bool;
}
