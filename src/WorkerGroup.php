<?php
declare(strict_types=1);

namespace CT\AmpCluster;

final readonly class WorkerGroup
{
    public function __construct(
        public string $workerClass,
        public WorkerTypeEnum $workerType,
        public int $workerGroupId,
        public int $minWorkers,
        public int $maxWorkers,
        public string $groupName,
        /**
         * @var int[]
         */
        public array $jobGroups = []
    ) {}
    
}