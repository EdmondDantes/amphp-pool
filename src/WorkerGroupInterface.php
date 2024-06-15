<?php
declare(strict_types=1);

namespace CT\AmpCluster;

use CT\AmpCluster\Worker\PickupStrategy\PickupStrategyInterface;
use CT\AmpCluster\Worker\RestartStrategy\RestartStrategyInterface;
use CT\AmpCluster\Worker\ScalingStrategy\ScalingStrategyInterface;

/**
 * Worker Group Interface, which defines the configuration of a worker group.
 */
interface WorkerGroupInterface
{
    public function getEntryPointClass(): string;
    public function getWorkerType(): WorkerTypeEnum;
    public function getWorkerGroupId(): int;
    public function getMinWorkers(): int;
    public function getMaxWorkers(): int;
    public function getGroupName(): string;
    
    /**
     * @return array<int>
     */
    public function getJobGroups(): array;
    
    public function getPickupStrategy(): ?PickupStrategyInterface;
    
    public function getRestartStrategy(): ?RestartStrategyInterface;
    
    public function getScalingStrategyClass(): ?ScalingStrategyInterface;
    
    public function defineGroupName(string $groupName): self;
    
    public function defineWorkerGroupId(int $workerGroupId): self;
    
    public function definePickupStrategy(PickupStrategyInterface $pickupStrategy): self;
    
    public function defineRestartStrategy(RestartStrategyInterface $restartStrategy): self;
    
    public function defineScalingStrategy(ScalingStrategyInterface $scalingStrategy): self;
}