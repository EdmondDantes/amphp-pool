<?php
declare(strict_types=1);

namespace CT\AmpPool;

use CT\AmpPool\JobIpc\JobClientInterface;
use CT\AmpPool\Strategies\JobExecutor\JobExecutorInterface;
use CT\AmpPool\Strategies\PickupStrategy\PickupStrategyInterface;
use CT\AmpPool\Strategies\RestartStrategy\RestartStrategyInterface;
use CT\AmpPool\Strategies\RunnerStrategy\RunnerStrategyInterface;
use CT\AmpPool\Strategies\ScalingStrategy\ScalingStrategyInterface;
use CT\AmpPool\Strategies\SocketStrategy\SocketStrategyInterface;
use CT\AmpPool\Strategies\WorkerStrategyInterface;

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

    public function getSocketStrategy(): ?SocketStrategyInterface;

    public function getJobExecutor(): ?JobExecutorInterface;

    public function getJobClient(): ?JobClientInterface;

    public function defineGroupName(string $groupName): self;

    public function defineWorkerGroupId(int $workerGroupId): self;

    public function defineMaxWorkers(int $maxWorkers): self;

    public function defineRunnerStrategy(RunnerStrategyInterface $runnerStrategy): self;

    public function definePickupStrategy(PickupStrategyInterface $pickupStrategy): self;

    public function defineRestartStrategy(RestartStrategyInterface $restartStrategy): self;

    public function defineScalingStrategy(ScalingStrategyInterface $scalingStrategy): self;

    public function defineJobExecutor(JobExecutorInterface $jobRunner): self;

    public function defineJobClient(JobClientInterface $jobClient): self;

    public function defineSocketStrategy(SocketStrategyInterface $socketStrategy): self;

    /**
     * @return WorkerStrategyInterface[]
     */
    public function getWorkerStrategies(): array;
}
