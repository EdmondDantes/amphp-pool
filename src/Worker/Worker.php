<?php
declare(strict_types=1);

namespace CT\AmpPool\Worker;

use Amp\Cancellation;
use Amp\DeferredCancellation;
use Amp\DeferredFuture;
use Amp\Pipeline\ConcurrentIterator;
use Amp\Pipeline\Queue;
use Amp\Sync\Channel;
use CT\AmpPool\Internal\Messages\MessagePingPong;
use CT\AmpPool\Internal\Messages\MessageShutdown;
use CT\AmpPool\PoolState\PoolStateReadableInterface;
use CT\AmpPool\PoolState\PoolStateStorage;
use CT\AmpPool\Strategies\WorkerStrategyInterface;
use CT\AmpPool\Worker\Internal\WorkerLogHandler;
use CT\AmpPool\Worker\WorkerState\WorkersInfo;
use CT\AmpPool\Worker\WorkerState\WorkersInfoInterface;
use CT\AmpPool\Worker\WorkerState\WorkerStateStorage;
use CT\AmpPool\Worker\WorkerState\WorkerStateStorageInterface;
use CT\AmpPool\WorkerEventEmitter;
use CT\AmpPool\WorkerEventEmitterInterface;
use CT\AmpPool\WorkerGroup;
use CT\AmpPool\WorkerTypeEnum;
use Psr\Log\LoggerInterface;
use Revolt\EventLoop;

/**
 * Abstraction of Worker Representation within the worker process.
 * This class should not be used within the process that creates workers!
 *
 * @template-covariant TReceive
 * @template TSend
 * @implements Channel<TReceive, TSend>
 */
class Worker                        implements WorkerInterface
{
    protected readonly DeferredCancellation $loopCancellation;
    
    /** @var Queue<TReceive> */
    protected readonly Queue $queue;
    
    /** @var ConcurrentIterator<TReceive> */
    protected readonly ConcurrentIterator $iterator;
    
    private LoggerInterface $logger;
    private PoolStateReadableInterface $poolState;
    private WorkerStateStorageInterface $workerStateStorage;
    private WorkersInfoInterface $workersInfo;
    private WorkerEventEmitterInterface $eventEmitter;
    
    private bool $isStopped         = false;
    
    public function __construct(
        private readonly int     $id,
        private readonly Channel $ipcChannel,
        private readonly WorkerGroup $group,
        /**
         * @var array<int, WorkerGroup>
         */
        private readonly array $groupsScheme,
        LoggerInterface        $logger = null
    ) {
        $this->queue                = new Queue();
        $this->iterator             = $this->queue->iterate();
        $this->loopCancellation     = new DeferredCancellation();
        
        $this->poolState            = new PoolStateStorage;
        $this->workerStateStorage   = new WorkerStateStorage($this->id, $this->group->getWorkerGroupId(), true);
        $this->workersInfo          = new WorkersInfo;
        $this->eventEmitter         = new WorkerEventEmitter;
        
        if($logger !== null) {
            $this->logger           = $logger;
        } else {
            $this->logger           = new \Monolog\Logger('worker-'.$id);
            $this->logger->pushHandler(new WorkerLogHandler($this->ipcChannel));
        }
    }
    
    public function initWorker(): void
    {
        $this->initWorkerStrategies();
        WorkerGroup::startStrategies($this->groupsScheme);
    }

    public function sendMessageToWatcher(mixed $message): void
    {
        $this->ipcChannel->send($message);
    }
    
    public function getWatcherChannel(): Channel
    {
        return $this->ipcChannel;
    }
    
    /**
     * @return array<int, WorkerGroup>
     */
    public function getGroupsScheme(): array
    {
        return $this->groupsScheme;
    }
    
    public function getPoolStateStorage(): PoolStateReadableInterface
    {
        return $this->poolState;
    }
    
    public function getWorkerStateStorage(): WorkerStateStorageInterface
    {
        return $this->workerStateStorage;
    }
    
    public function getWorkersInfo(): WorkersInfoInterface
    {
        return $this->workersInfo;
    }
    
    public function getLogger(): LoggerInterface
    {
        return $this->logger;
    }
    
    public function getWorkerId(): int
    {
        return $this->id;
    }
    
    public function getWorkerGroup(): WorkerGroup
    {
        return $this->group;
    }
    
    public function getWorkerGroupId(): int
    {
        return $this->group->getWorkerGroupId();
    }
    
    public function getWorkerType(): WorkerTypeEnum
    {
        return $this->group->getWorkerType();
    }
    
    public function getWorkerEventEmitter(): WorkerEventEmitterInterface
    {
        return $this->eventEmitter;
    }
    
    public function getAbortCancellation(): Cancellation
    {
        return $this->loopCancellation->getCancellation();
    }
    
    public function mainLoop(): void
    {
        $abortCancellation          = $this->loopCancellation->getCancellation();
        
        try {
            while ($message = $this->ipcChannel->receive($abortCancellation)) {
                
                if($message instanceof MessagePingPong) {
                    $this->ipcChannel->send(new MessagePingPong);
                    continue;
                }
                
                if($message instanceof MessageShutdown) {
                    $this->logger->notice('Worker #'.$this->id.' received shutdown message');
                    break;
                }
                
                $this->eventEmitter->emitWorkerEvent($message, $this->id);
            }
        } catch (\Throwable) {
            // IPC Channel manually closed
        } finally {
            $this->eventEmitter->free();
            
            if(false === $this->loopCancellation->isCancelled()) {
                $this->loopCancellation->cancel();
            }
            
            if(false === $this->queue->isComplete()) {
                $this->queue->complete();
            }
        }
    }
    
    public function awaitTermination(?Cancellation $cancellation = null): void
    {
        $deferredFuture             = new DeferredFuture();
        $loopCancellation           = $this->loopCancellation->getCancellation();
        
        $loopId                     = $loopCancellation->subscribe($deferredFuture->complete(...));
        $cancellationId             = $cancellation?->subscribe(static fn () => $loopCancellation->unsubscribe($loopId));
        
        try {
            $deferredFuture->getFuture()->await($cancellation);
        } finally {
            /** @psalm-suppress PossiblyNullArgument $cancellationId is not null if $cancellation is not null. */
            $cancellation?->unsubscribe($cancellationId);
            $loopCancellation->unsubscribe($loopId);
        }
    }
    
    public function __destruct()
    {
        EventLoop::queue($this->stop(...));
    }
    
    public function stop(): void
    {
        if($this->isStopped) {
            return;
        }
        
        $this->isStopped            = true;
        
        if(false === $this->loopCancellation->isCancelled()) {
            $this->loopCancellation->cancel();
        }
        
        WorkerGroup::stopStrategies($this->groupsScheme, $this->logger);
    }
    
    public function isStopped(): bool
    {
        return $this->isStopped;
    }
    
    public function onClose(\Closure $onClose): void
    {
        $this->loopCancellation->getCancellation()->subscribe(static fn () => $onClose());
    }
    
    public function __toString(): string
    {
        return $this->group->getGroupName().'-'.$this->id;
    }
    
    protected function initWorkerStrategies(): void
    {
        foreach ($this->group->getWorkerStrategies() as $strategy) {
            if($strategy instanceof WorkerStrategyInterface) {
                $strategy->setWorker($this)->setWorkerGroup($this->group);
            }
        }
    }
}