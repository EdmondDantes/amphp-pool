<?php
declare(strict_types=1);

namespace CT\AmpServer;

use Amp\Cancellation;

interface WorkerPoolInterface
{
    public function describeGroup(WorkerTypeEnum $type, int $minCount = 1, int $maxCount = null, string $groupName = null, array $jobGroups = []): self;
    public function describeReactorGroup(int $minCount = 1, int $maxCount = null, string $groupName = null, int $jobGroup = null): self;
    public function describeJobGroup(int $minCount = 1, int $maxCount = null, string $groupName = null): self;
    public function describeServiceGroup(string $groupName, int $minCount = 1, int $maxCount = null, array $jobGroups = []): self;
    
    /**
     * @return WorkerGroup[]
     */
    public function getGroupsScheme(): array;
    public function validateGroupsScheme(): void;
    
    public function run(): void;
    public function stop(?Cancellation $cancellation = null): void;
    
    public function getMessageIterator(): iterable;
    
    public function getWorkers(): array;
}