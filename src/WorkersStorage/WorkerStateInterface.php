<?php
declare(strict_types=1);

namespace CT\AmpPool\WorkersStorage;

interface WorkerStateInterface
{
    public static function instanciateFromStorage(WorkersStorageInterface $storage, int $workerId, bool $reviewOnly = false): WorkerStateInterface;
    
    public static function unpackItem(string $packedItem): WorkerStateInterface;
    
    /**
     * Returns the size of the item.
     */
    public static function getItemSize(): int;
    
    public function read(): static;
    public function update(): static;
    
    public function updateStateSegment(): static;
    public function updateTimeSegment(): static;
    
    public function updateMemorySegment(): static;
    
    public function updateConnectionsSegment(): static;
    
    public function updateJobSegment(): static;
    
    function updateShouldBeStarted(bool $shouldBeStarted): static;
    
    function increaseAndUpdateShutdownErrors(int $count = 1): static;
    
    public function getWorkerId(): int;
    
    public function getGroupId(): int;
    
    public function setGroupId(int $groupId): static;
    
    public function isShouldBeStarted(): bool;
    
    public function markAsShouldBeStarted(): static;
    
    public function isReady(): bool;
    
    public function markAsReady(): static;
    
    public function markAsUnReady(): static;
    
    public function markAsShutdown(): static;
    
    public function getPid(): int;
    
    public function setPid(int $pid): static;
    
    public function getTotalReloaded(): int;
    
    public function setTotalReloaded(int $totalReloaded): static;
    
    public function incrementTotalReloaded(): static;
    
    public function getShutdownErrors(): int;
    
    public function setShutdownErrors(int $shutdownErrors): static;
    
    public function incrementShutdownErrors(): static;
    
    public function getWeight(): int;
    
    public function setWeight(int $weight): static;
    
    public function increaseWeight(int $weight): static;
    
    public function decreaseWeight(int $weight): static;
    
    public function getFirstStartedAt(): int;
    
    public function setFirstStartedAt(int $firstStartedAt): static;
    
    public function getStartedAt(): int;
    
    public function setStartedAt(int $startedAt): static;
    
    public function getFinishedAt(): int;
    
    public function setFinishedAt(int $finishedAt): static;
    
    public function getUpdatedAt(): int;
    
    public function setUpdatedAt(int $updatedAt): static;
    
    public function getPhpMemoryUsage(): int;
    
    public function setPhpMemoryUsage(int $phpMemoryUsage): static;
    
    public function getPhpMemoryPeakUsage(): int;
    
    public function setPhpMemoryPeakUsage(int $phpMemoryPeakUsage): static;
    
    public function getConnectionsAccepted(): int;
    
    public function setConnectionsAccepted(int $connectionsAccepted): static;
    
    public function getConnectionsProcessed(): int;
    
    public function setConnectionsProcessed(int $connectionsProcessed): static;
    
    public function getConnectionsErrors(): int;
    
    public function setConnectionsErrors(int $connectionsErrors): static;
    
    public function getConnectionsRejected(): int;
    
    public function setConnectionsRejected(int $connectionsRejected): static;
    
    public function getConnectionsProcessing(): int;
    
    public function setConnectionsProcessing(int $connectionsProcessing): static;
    
    public function getJobAccepted(): int;
    
    public function setJobAccepted(int $jobAccepted): static;
    
    public function getJobProcessed(): int;
    
    public function setJobProcessed(int $jobProcessed): static;
    
    public function getJobProcessing(): int;
    
    public function setJobProcessing(int $jobProcessing): static;
    
    public function getJobErrors(): int;
    
    public function setJobErrors(int $jobErrors): static;
    
    public function incrementJobErrors(): static;
    
    public function getJobRejected(): int;
    
    public function setJobRejected(int $jobRejected): static;
    
    public function jobEnqueued(int $weight, bool $canAcceptMoreJobs): void;
    
    public function jobDequeued(int $weight, bool $canAcceptMoreJobs): void;
    
    public function initDefaults(): static;
}
