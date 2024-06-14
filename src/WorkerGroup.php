<?php
declare(strict_types=1);

namespace CT\AmpCluster;

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
        private ?string        $pickupStrategyClass = null,
        private ?string        $restartStrategyClass = null,
        private ?string        $scalingStrategyClass = null
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
    
    public function getPickupStrategyClass(): ?string
    {
        return $this->pickupStrategyClass;
    }
    
    public function getRestartStrategyClass(): ?string
    {
        return $this->restartStrategyClass;
    }
    
    public function getScalingStrategyClass(): ?string
    {
        return $this->scalingStrategyClass;
    }
}