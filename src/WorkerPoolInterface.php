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
    
    public function getMessageIterator(): iterable;
    
    /**
     * @return WorkerDescriptor[]
     */
    public function getWorkers(): array;
}