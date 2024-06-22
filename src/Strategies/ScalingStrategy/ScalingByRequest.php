<?php
declare(strict_types=1);

namespace CT\AmpPool\Strategies\ScalingStrategy;

use CT\AmpPool\Strategies\WorkerStrategyAbstract;
use CT\AmpPool\WorkerEventEmitterAwareInterface;
use Revolt\EventLoop;

final class ScalingByRequest        extends WorkerStrategyAbstract
                                    implements ScalingStrategyInterface
{
    private int $lastScalingRequest   = 0;
    private mixed $handleScalingRequest;
    private string $decreaseCallbackId = '';
    
    public function __construct(
        private readonly int $decreaseTimeout = 5 * 60,
        private readonly int $decreaseCheckInterval = 5 * 60,
    ) {}
    
    
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
    
    public function onStarted(): void
    {
        $workerPool                 = $this->getWorkerPool();
        
        if($workerPool instanceof WorkerEventEmitterAwareInterface) {
            $this->handleScalingRequest = $this->handleScalingRequest(...);
            $workerPool->getWorkerEventEmitter()->addWorkerEventListener($this->handleScalingRequest);
            
            $this->decreaseCallbackId = EventLoop::repeat($this->decreaseCheckInterval, $this->decreaseWorkers(...));
        }
    }
    
    public function onStopped(): void
    {
        $workerPool                 = $this->getWorkerPool();
    
        if($workerPool instanceof WorkerEventEmitterAwareInterface && $this->handleScalingRequest !== null) {
            $workerPool->getWorkerEventEmitter()->removeWorkerEventListener($this->handleScalingRequest);
        }
        
        if($this->decreaseCallbackId !== '') {
            EventLoop::cancel($this->decreaseCallbackId);
        }
    }
    
    public function __destruct()
    {
        if($this->decreaseCallbackId !== '') {
            EventLoop::cancel($this->decreaseCallbackId);
        }
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
        
        $this->lastScalingRequest   = \time();
        
        $workerPool                 = $this->getWorkerPool();
        
        if($workerPool === null) {
            return;
        }
        
        $workerPool->scaleWorkers($workerGroup->getWorkerGroupId(), 1);
    }
    
    private function decreaseWorkers(): void
    {
        $workerPool                 = $this->getWorkerPool();
        
        if($workerPool === null) {
            return;
        }
        
        if($this->lastScalingRequest + $this->decreaseTimeout <= \time()) {
            return;
        }

        $runningWorkers             = $workerPool->countWorkers($this->getWorkerGroup()?->getWorkerGroupId(), onlyRunning: true);
        
        if($runningWorkers <= $this->getWorkerGroup()->getMinWorkers()) {
            return;
        }
        
        $workerPool->scaleWorkers(
            $this->getWorkerGroup()->getWorkerGroupId(), $this->getWorkerGroup()->getMinWorkers()
        );
    }
}