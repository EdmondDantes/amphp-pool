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
    protected readonly DeferredCancellation $mainCancellation;
    private readonly DeferredFuture $workerFuture;
    
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
        $this->mainCancellation     = new DeferredCancellation;
        $this->workerFuture         = new DeferredFuture;
        
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
        return $this->mainCancellation->getCancellation();
    }
    
    public function mainLoop(): void
    {
        $abortCancellation          = $this->mainCancellation->getCancellation();
        
        try {
            while ($message = $this->ipcChannel->receive($abortCancellation)) {
                
                if($message instanceof MessagePingPong) {
                    $this->ipcChannel->send(new MessagePingPong);
                    continue;
                }
                
                if($message instanceof MessageShutdown) {
                    //$this->logger->notice('Worker #'.$this->id.' received shutdown message');
                    break;
                }
                
                $this->eventEmitter->emitWorkerEvent($message, $this->id);
            }
        } catch (\Throwable $exception) {
            // IPC Channel manually closed
            if(false === $this->workerFuture->isComplete()) {
                $this->workerFuture->error($exception);
            }
        } finally {
            $this->stop();
        }
    }
    
    public function awaitTermination(?Cancellation $cancellation = null): void
    {
        $this->workerFuture->getFuture()->await($cancellation);
    }
    
    public function stop(): void
    {
        if($this->isStopped) {
            return;
        }
        
        $this->isStopped            = true;
        
        if(false === $this->mainCancellation->isCancelled()) {
            $this->mainCancellation->cancel();
        }
        
        try {
            WorkerGroup::stopStrategies($this->groupsScheme, $this->logger);
        } finally {
            $this->eventEmitter->free();
            
            if(false === $this->workerFuture->isComplete()) {
                $this->workerFuture->complete();
            }
            
            if(false === $this->queue->isComplete()) {
                $this->queue->complete();
            }
        }
    }
    
    public function isStopped(): bool
    {
        return $this->isStopped;
    }
    
    public function onClose(\Closure $onClose): void
    {
        $this->mainCancellation->getCancellation()->subscribe(static fn () => $onClose());
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