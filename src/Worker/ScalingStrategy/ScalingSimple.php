<?php
declare(strict_types=1);

namespace CT\AmpPool\Worker\ScalingStrategy;

use CT\AmpPool\Worker\WorkerStrategyAbstract;
use CT\AmpPool\WorkerEventEmitterAwareInterface;
use CT\AmpPool\WorkerPoolInterface;

final class ScalingSimple           extends WorkerStrategyAbstract
                                    implements ScalingStrategyInterface
{
    public function requestScaling(int $fromWorkerId = 0): bool
    {
        $workerGroup                = $this->getWorkerGroup();
        
        if($workerGroup === null) {
            return false;
        }
        
        [$lowestWorkerId, $highestWorkerId] = $this->getPoolStateStorage()?->findGroupState($workerGroup->getWorkerGroupId());
        
        if(($highestWorkerId - $lowestWorkerId) >= $workerGroup->getMaxWorkers()) {
            return false;
        }
        
        $this->getWorker()?->sendMessageToWatcher(new ScalingRequest($this->getWorker()?->getWorkerId()));
        
        return true;
    }
    
    public function setWorkerPool(WorkerPoolInterface $workerPool): WorkerStrategyAbstract
    {
        if($workerPool instanceof WorkerEventEmitterAwareInterface) {
            $workerPool->getWorkerEventEmitter()->addWorkerEventListener($this->handleScalingRequest(...));
        }
        
        return parent::setWorkerPool($workerPool);
    }
    
    public function adjustWorkerCount(): int
    {
        // TODO: Implement adjustWorkerCount() method.
    }
    
    private function handleScalingRequest(mixed $message): void
    {
        if($message instanceof ScalingRequest === false) {
            return;
        }
        
        $workerGroup                = $this->getWorkerGroup();
        
        if($workerGroup === null) {
            return;
        }
        
        $workerPool                 = $this->getWorkerPool();
        
        if($workerPool === null) {
            return;
        }
        
        
    }
}