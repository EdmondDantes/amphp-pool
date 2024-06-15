<?php
declare(strict_types=1);

namespace CT\AmpCluster;

use Amp\Cancellation;
use CT\AmpCluster\Worker\WorkerDescriptor;

interface WorkerPoolInterface
{
    public function describeGroup(WorkerGroupInterface $group): self;
    
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