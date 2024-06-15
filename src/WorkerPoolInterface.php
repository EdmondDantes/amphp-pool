<?php
declare(strict_types=1);

namespace CT\AmpCluster;

use Amp\Cancellation;
use CT\AmpCluster\Worker\WorkerDescriptor;

interface WorkerPoolInterface
{
    public function describeGroup(string $workerClass, WorkerTypeEnum $type, int $minCount = 1, int $maxCount = null, string $groupName = null, array $jobGroups = []): self;
    public function describeReactorGroup(string $workerClass, int $minCount = 1, int $maxCount = null, string $groupName = null, int $jobGroup = null): self;
    public function describeJobGroup(string $workerClass, int $minCount = 1, int $maxCount = null, string $groupName = null): self;
    public function describeServiceGroup(string $workerClass, string $groupName, int $minCount = 1, int $maxCount = null, array $jobGroups = []): self;
    
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