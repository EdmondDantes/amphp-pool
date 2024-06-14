<?php
declare(strict_types=1);

namespace CT\AmpCluster;

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
    
    public function getRestartStrategyClass(): ?string;
    
    public function getScalingStrategyClass(): ?string;
}