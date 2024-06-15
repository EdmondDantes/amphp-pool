<?php
declare(strict_types=1);

namespace CT\AmpPool;

use CT\AmpPool\Worker\PickupStrategy\PickupStrategyInterface;
use CT\AmpPool\Worker\RestartStrategy\RestartStrategyInterface;
use CT\AmpPool\Worker\RunnerStrategy\RunnerStrategyInterface;
use CT\AmpPool\Worker\ScalingStrategy\ScalingStrategyInterface;

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
    
    public function getRunnerStrategy(): ?RunnerStrategyInterface;
    
    public function getPickupStrategy(): ?PickupStrategyInterface;
    
    public function getRestartStrategy(): ?RestartStrategyInterface;
    
    public function getScalingStrategy(): ?ScalingStrategyInterface;
    
    public function defineGroupName(string $groupName): self;
    
    public function defineWorkerGroupId(int $workerGroupId): self;
    
    public function defineMaxWorkers(int $maxWorkers): self;
    
    public function defineRunnerStrategy(RunnerStrategyInterface $runnerStrategy): self;
    
    public function definePickupStrategy(PickupStrategyInterface $pickupStrategy): self;
    
    public function defineRestartStrategy(RestartStrategyInterface $restartStrategy): self;
    
    public function defineScalingStrategy(ScalingStrategyInterface $scalingStrategy): self;
}