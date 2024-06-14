<?php
declare(strict_types=1);

namespace CT\AmpCluster;

use CT\AmpCluster\Worker\RestartPolicy\RestartAlways;
use CT\AmpCluster\Worker\RestartPolicy\RestartPolicyInterface;

/**
 * Data structure for describing a group of workers.
 */
final readonly class WorkerGroup implements WorkerGroupInterface
{
    public function __construct(
        private string         $entryPointClass,
        private WorkerTypeEnum $workerType,
        private int            $workerGroupId,
        private int            $minWorkers,
        private int            $maxWorkers,
        private string         $groupName,
        /**
         * @var int[]
         */
        private array          $jobGroups = [],
        private ?string $restartPolicyClass = null
    ) {}
    
    public function getEntryPointClass(): string
    {
        return $this->entryPointClass;
    }
    
    public function getWorkerType(): WorkerTypeEnum
    {
        return $this->workerType;
    }
    
    public function getWorkerGroupId(): int
    {
        return $this->workerGroupId;
    }
    
    public function getMinWorkers(): int
    {
        return $this->minWorkers;
    }
    
    public function getMaxWorkers(): int
    {
        return $this->maxWorkers;
    }
    
    public function getGroupName(): string
    {
        return $this->groupName;
    }
    
    public function getJobGroups(): array
    {
        return $this->jobGroups;
    }
    
    public function getRestartPolicyClass(): ?string
    {
        return $this->restartPolicyClass;
    }
}