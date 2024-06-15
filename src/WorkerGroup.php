<?php
declare(strict_types=1);

namespace CT\AmpCluster;

use CT\AmpCluster\Worker\PickupStrategy\PickupStrategyInterface;
use CT\AmpCluster\Worker\RestartStrategy\RestartStrategyInterface;
use CT\AmpCluster\Worker\ScalingStrategy\ScalingStrategyInterface;

/**
 * Data structure for describing a group of workers.
 */
final class WorkerGroup             implements WorkerGroupInterface
{
    public function __construct(
        private readonly string         $entryPointClass,
        private readonly WorkerTypeEnum $workerType,
        private readonly int            $minWorkers = 0,
        private int                     $maxWorkers = 0,
        private string                  $groupName = '',
        /**
         * @var int[]
         */
        private readonly array          $jobGroups = [],
        private ?PickupStrategyInterface $pickupStrategy = null,
        private ?RestartStrategyInterface $restartStrategy = null,
        private ?ScalingStrategyInterface $scalingStrategy = null,
        private int                     $workerGroupId = 0,
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
    
    public function getPickupStrategy(): ?PickupStrategyInterface
    {
        return $this->pickupStrategy;
    }
    
    public function getRestartStrategy(): ?RestartStrategyInterface
    {
        return $this->restartStrategy;
    }
    
    public function getScalingStrategy(): ?ScalingStrategyInterface
    {
        return $this->scalingStrategy;
    }
    
    public function defineGroupName(string $groupName): self
    {
        if($this->groupName !== '') {
            throw new \LogicException('Group name is already defined');
        }
        
        $this->groupName            = $groupName;
        
        return $this;
    }
    
    public function defineWorkerGroupId(int $workerGroupId): self
    {
        if($workerGroupId <= 0) {
            throw new \InvalidArgumentException('Worker group ID must be a positive integer');
        }
        
        if($this->workerGroupId !== 0) {
            throw new \LogicException('Worker group ID is already defined');
        }
        
        $this->workerGroupId        = $workerGroupId;
        
        return $this;
    }
    
    public function defineMaxWorkers(int $maxWorkers): self
    {
        if($maxWorkers <= 0) {
            throw new \InvalidArgumentException('Max workers must be a positive integer');
        }
        
        if($this->maxWorkers !== 0) {
            throw new \LogicException('Max workers is already defined');
        }
        
        $this->maxWorkers           = $maxWorkers;
        
        return $this;
    }
    
    public function definePickupStrategy(PickupStrategyInterface $pickupStrategy): self
    {
        if($this->pickupStrategy !== null) {
            throw new \LogicException('Pickup strategy is already defined');
        }
        
        $this->pickupStrategy       = $pickupStrategy;
        
        return $this;
    }
    
    public function defineRestartStrategy(RestartStrategyInterface $restartStrategy): self
    {
        if($this->restartStrategy !== null) {
            throw new \LogicException('Restart strategy is already defined');
        }
        
        $this->restartStrategy      = $restartStrategy;
        
        return $this;
    }
    
    public function defineScalingStrategy(ScalingStrategyInterface $scalingStrategy): self
    {
        if($this->scalingStrategy !== null) {
            throw new \LogicException('Scaling strategy is already defined');
        }
        
        $this->scalingStrategy      = $scalingStrategy;
        
        return $this;
    }
}