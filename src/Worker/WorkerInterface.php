<?php
declare(strict_types=1);

namespace CT\AmpPool\Worker;

use Amp\Cancellation;
use Amp\Sync\Channel;
use CT\AmpPool\WorkerEventEmitterAwareInterface;
use CT\AmpPool\WorkerGroup;
use CT\AmpPool\WorkersStorage\WorkersStorageAwareInterface;
use CT\AmpPool\WorkersStorage\WorkerStateInterface;
use CT\AmpPool\WorkerTypeEnum;
use Psr\Log\LoggerInterface;

interface WorkerInterface extends WorkerEventEmitterAwareInterface, WorkersStorageAwareInterface
{
    /**
     * @return array<int, WorkerGroup>
     */
    public function getGroupsScheme(): array;

    public function sendMessageToWatcher(mixed $message): void;

    public function getWatcherChannel(): Channel;

    public function getWorkerState(): WorkerStateInterface;

    public function getWorkerId(): int;
    public function getWorkerGroup(): WorkerGroup;
    public function getWorkerGroupId(): int;
    public function getWorkerType(): WorkerTypeEnum;

    public function getAbortCancellation(): Cancellation;

    public function mainLoop(): void;

    public function getLogger(): LoggerInterface;

    public function awaitShutdown(): void;

    public function awaitTermination(?Cancellation $cancellation = null): void;

    public function initiateTermination(?\Throwable $throwable = null): void;

    public function stop(): void;

    public function isStopped(): bool;

    public function onClose(\Closure $onClose): void;

    public function addPeriodicTask(float $delay, \Closure $task): int;

    public function cancelPeriodicTask(int $taskId): void;

    public function defineSoftShutdownHandler(callable $handler): void;
}
