<?php
declare(strict_types=1);

namespace CT\AmpPool;

use Amp\Cancellation;
use Amp\Parallel\Ipc\IpcHub;
use CT\AmpPool\Worker\WorkerDescriptor;

interface WorkerPoolInterface
{
    public function describeGroup(WorkerGroupInterface $group): self;
    
    public function getIpcHub(): IpcHub;
    
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
     * @return WorkerDescriptor[]
     */
    public function getWorkers(): array;
}