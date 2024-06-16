<?php
declare(strict_types=1);

namespace CT\AmpPool\Worker\WorkerState;

use CT\AmpPool\Worker\WorkerState\Exceptions\WorkerStateNotAvailable;
use CT\AmpPool\Worker\WorkerState\Exceptions\WorkerStateReadFailed;

/**
 * The class provides information about the state of the workers.
 */
final class WorkersInfo             implements WorkersInfoInterface
{
    /**
     * @var array<int, WorkerStateStorage>
     */
    private array $storages         = [];
    
    public function getWorkerState(int $workerId): ?WorkerState
    {
        try {
            $storage                = $this->getWorkerStateStorage($workerId);
            $storage->update();
            
            return new WorkerState($storage->isWorkerReady(), $storage->getJobCount(), $storage->getWorkerGroupId());
        } catch (WorkerStateNotAvailable|WorkerStateReadFailed) {
            return null;
        }
    }
    
    private function getWorkerStateStorage(int $workerId): WorkerStateStorage
    {
        if(array_key_exists($workerId, $this->storages)) {
            return $this->storages[$workerId];
        }
        
        return $this->storages[$workerId] = new WorkerStateStorage($workerId);
    }
}