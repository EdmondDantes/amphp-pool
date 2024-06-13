<?php
declare(strict_types=1);

namespace CT\AmpServer;

final readonly class WorkerGroup
{
    public function __construct(
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