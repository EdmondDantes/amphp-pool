<?php
declare(strict_types=1);

namespace CT\AmpPool;

use Amp\Cancellation;
use Amp\Parallel\Context\Context;
use Amp\Parallel\Ipc\IpcHub;
use CT\AmpPool\Internal\WorkerProcessContext;
use CT\AmpPool\PoolState\PoolStateReadableInterface;
use CT\AmpPool\Worker\WorkerState\WorkersInfoInterface;
use Psr\Log\LoggerInterface as PsrLogger;

interface WorkerPoolInterface       extends WorkerEventEmitterAwareInterface
{
    public function describeGroup(WorkerGroupInterface $group): self;
    
    public function getIpcHub(): IpcHub;
    
    public function getLogger(): PsrLogger|null;
    
    public function getPoolStateStorage(): PoolStateReadableInterface;
    
    public function getWorkersInfo(): WorkersInfoInterface;
    
    /**
     * @return WorkerGroup[]
     */
    public function getGroupsScheme(): array;
    public function validateGroupsScheme(): void;
    
    public function run(): void;
    public function stop(?Cancellation $cancellation = null): void;
    
    /**
     * The method stops all workers and restarts them.
     *
     * @param Cancellation|null $cancellation
     *
     * @return void
     */
    public function restart(?Cancellation $cancellation = null): void;
    
    public function countWorkers(int $groupId, bool $onlyRunning = false, bool $notRunning = false): int;
    
    public function getMainCancellation(): ?Cancellation;
    
    /**
     * The method will block current fiber until all workers are stopped.
     *
     * @param Cancellation|null $cancellation
     *
     * @return void
     */
    public function awaitTermination(Cancellation $cancellation = null): void;
    
    /**
     * @return int[]
     */
    public function getWorkers(): array;
    
    public function findWorkerContext(int $workerId): Context|null;
    
    public function findWorkerCancellation(int $workerId): Cancellation|null;
    
    /**
     * Scale workers in the group.
     * Returns the number of workers that were actually scaled (started or shutdown).
     *
     * The parameter $count can be negative
     * in this case, the method will try to stop the workers.
     *
     * @param       int $groupId
     * @param       int $count
     *
     * @return      int
     */
    public function scaleWorkers(int $groupId, int $count): int;
}