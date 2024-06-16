<?php
declare(strict_types=1);

namespace CT\AmpPool;

use Amp\Cancellation;
use Amp\Parallel\Ipc\IpcHub;
use CT\AmpPool\PoolState\PoolStateReadableInterface;
use CT\AmpPool\Worker\WorkerDescriptor;
use CT\AmpPool\Worker\WorkerInterface;
use CT\AmpPool\Worker\WorkerState\WorkersInfoInterface;

interface WorkerPoolInterface
{
    public function describeGroup(WorkerGroupInterface $group): self;
    
    public function getIpcHub(): IpcHub;
    
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
    
    /**
     * The method will block current fiber until all workers are stopped.
     *
     * @return void
     */
    public function awaitTermination(): void;
    
    /**
     * @return WorkerInterface[]
     */
    public function getWorkers(): array;
}