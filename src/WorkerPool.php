<?php
declare(strict_types=1);

namespace CT\AmpPool;

use Amp\Cancellation;
use Amp\CancelledException;
use Amp\Cluster\ClusterException;
use Amp\Cluster\ClusterWorkerMessage;
use Amp\CompositeException;
use Amp\DeferredCancellation;
use Amp\Future;
use Amp\Parallel\Context\Context;
use Amp\Parallel\Context\ContextException;
use Amp\Parallel\Context\ContextFactory;
use Amp\Parallel\Context\ContextPanicError;
use Amp\Parallel\Context\DefaultContextFactory;
use Amp\Parallel\Ipc\IpcHub;
use Amp\Parallel\Ipc\LocalIpcHub;
use Amp\Parallel\Worker\TaskFailureThrowable;
use Amp\Pipeline\ConcurrentIterator;
use Amp\Pipeline\Queue;
use Amp\Sync\ChannelException;
use Amp\TimeoutCancellation;
use CT\AmpPool\Exceptions\FatalWorkerException;
use CT\AmpPool\Exceptions\TerminateWorkerException;
use CT\AmpPool\Exceptions\WorkerPoolException;
use CT\AmpPool\Internal\SocketPipe\SocketListenerProvider;
use CT\AmpPool\Internal\SocketPipe\SocketPipeProvider;
use CT\AmpPool\Internal\WorkerProcessContext;
use CT\AmpPool\PoolState\PoolStateReadableInterface;
use CT\AmpPool\PoolState\PoolStateStorage;
use CT\AmpPool\Strategies\PickupStrategy\PickupLeastJobs;
use CT\AmpPool\Strategies\RestartStrategy\RestartAlways;
use CT\AmpPool\Strategies\RunnerStrategy\DefaultRunner;
use CT\AmpPool\Strategies\ScalingStrategy\ScalingByRequest;
use CT\AmpPool\Strategies\WorkerStrategyInterface;
use CT\AmpPool\Worker\Internal\WorkerDescriptor;
use CT\AmpPool\Worker\WorkerState\WorkersInfo;
use CT\AmpPool\Worker\WorkerState\WorkersInfoInterface;
use Psr\Log\LoggerInterface as PsrLogger;
use Revolt\EventLoop;
use function Amp\async;
use function Amp\trapSignal;
use const Amp\Process\IS_WINDOWS;

/**
 * Worker Pool Manager Class.
 *
 * A worker pool allows you to create groups of processes belonging to different types of workers,
 * and then use them to perform tasks.
 *
 * @template-covariant TReceive
 * @template TSend
 */
class WorkerPool                    implements WorkerPoolInterface, WorkerEventEmitterAwareInterface
{
    protected int $workerStartTimeout = 5;
    protected int $workerStopTimeout  = 5;
    private int $lastGroupId        = 0;
    
    /**
     * @var WorkerDescriptor[]
     */
    protected array $workers        = [];
    
    /** @var Queue<ClusterWorkerMessage<TReceive, TSend>> */
    protected readonly Queue $queue;
    /** @var ConcurrentIterator<ClusterWorkerMessage<TReceive, TSend>> */
    private readonly ConcurrentIterator $iterator;
    private bool $running           = false;
    private SocketPipeProvider $provider;
    
    private ?SocketListenerProvider $listenerProvider = null;
    
    private ?PoolStateStorage $poolState    = null;
    
    private ?DeferredCancellation $mainCancellation = null;
    
    private WorkersInfoInterface $workersInfo;
    
    /**
     * @var WorkerGroupInterface[]
     */
    private array $groupsScheme             = [];
    
    private WorkerEventEmitterInterface $eventEmitter;
    
    public function __construct(
        protected readonly IpcHub $hub      = new LocalIpcHub(),
        protected ?ContextFactory $contextFactory = null,
        protected string|array $script      = '',
        protected ?PsrLogger $logger        = null
    ) {
        $this->provider             = new SocketPipeProvider($this->hub);
        $this->contextFactory       ??= new DefaultContextFactory(ipcHub: $this->hub);
        $this->workersInfo          = new WorkersInfo;
        $this->eventEmitter         = new WorkerEventEmitter;
        
        // For Windows, we should use the SocketListenerProvider instead of the SocketPipeProvider
        if(PHP_OS_FAMILY === 'Windows') {
            $this->listenerProvider = new SocketListenerProvider($this);
        }
    }
    
    public function getIpcHub(): IpcHub
    {
        return $this->hub;
    }
    
    public function getPoolStateStorage(): PoolStateReadableInterface
    {
        return $this->poolState;
    }
    
    public function getWorkersInfo(): WorkersInfoInterface
    {
        return $this->workersInfo;
    }
    
    public function describeGroup(WorkerGroupInterface $group): self
    {
        $group                      = clone $group;
        
        if(class_exists($group->getEntryPointClass()) === false) {
            throw new \Error("The worker class '{$group->getEntryPointClass()}' does not exist");
        }
        
        if($group->getMinWorkers() < 0) {
            throw new \Error('The minimum number of workers must be greater than zero');
        }
        
        if($group->getMaxWorkers() === 0) {
            $group->defineMaxWorkers($group->getMinWorkers());
        }
        
        if($group->getMaxWorkers() < $group->getMinWorkers()) {
            throw new \Error('The maximum number of workers must be greater than or equal to the minimum number of workers');
        }
        
        if($group->getMaxWorkers() === 0) {
            throw new \Error('The maximum number of workers must be greater than zero');
        }
        
        $groupId                    = ++$this->lastGroupId;
        
        if($group->getGroupName() === '') {
            // If group name undefined, use the worker class name without a namespace
            $groupName              = \strrchr($group->getEntryPointClass(), '\\');
            
            if($groupName === false) {
                $groupName          = 'Group'.$groupId;
            } else {
                $groupName          = \ucfirst(\substr($groupName, 1));
            }
            
            $group->defineGroupName($groupName);
        }
        
        $this->groupsScheme[$groupId] = $group->defineWorkerGroupId($groupId);
        
        return $this;
    }
    
    public function getGroupsScheme(): array
    {
        return $this->groupsScheme;
    }
    
    /**
     * @throws \Exception
     */
    public function validateGroupsScheme(): void
    {
        if(empty($this->groupsScheme)) {
            throw new \Exception('The worker groups scheme is empty');
        }
        
        $lastGroupId                = 0;
        
        foreach ($this->groupsScheme as $group) {
            
            if(class_exists($group->getEntryPointClass()) === false) {
                throw new \Exception("The worker class '{$group->getEntryPointClass()}' does not exist");
            }
            
            if($group->getWorkerGroupId() <= $lastGroupId) {
                throw new \Exception('The group ID must be greater than the previous group id');
            }
            
            $lastGroupId            = $group->getWorkerGroupId();
            
            if($group->getMinWorkers() < 0) {
                throw new \Exception('The minimum number of workers must be greater than zero or equal to zero');
            }
            
            if($group->getMaxWorkers() < $group->getMinWorkers()) {
                throw new \Exception('The maximum number of workers must be greater than or equal to the minimum number of workers');
            }
            
            if($group->getMaxWorkers() === 0) {
                throw new \Exception('The maximum number of workers must be greater than zero');
            }
            
            foreach ($group->getJobGroups() as $jobGroupId) {
                if(\array_key_exists($jobGroupId, $this->groupsScheme)) {
                    throw new \Exception("The job group id '{$jobGroupId}' is not found in the worker groups scheme");
                }
                
                if($jobGroupId === $group->getWorkerGroupId()) {
                    throw new \Exception("The job group id '{$jobGroupId}' must be different from the worker group id");
                }
            }
            
        }
    }
    
    /**
     * @throws \Throwable
     */
    public function run(): void
    {
        if ($this->running) {
            throw new \Exception('The server watcher is already running or has already run');
        }
        
        $this->validateGroupsScheme();
        $this->applyGroupScheme();
        
        if (count($this->workers) <= 0) {
            throw new \Exception('The number of workers must be greater than zero');
        }
        
        $this->running              = true;
        $this->mainCancellation     = new DeferredCancellation;

        try {
            
            if($this->poolState === null) {
                $this->poolState    = new PoolStateStorage(count($this->groupsScheme));
            }
            
            foreach ($this->workers as $worker) {
                if($worker->shouldBeStarted) {
                    $this->startWorker($worker);
                }
            }
            
            $this->updateGroupsState();
            
        } catch (\Throwable $exception) {
            $this->stop();
            throw $exception;
        }
    }
    
    public function getMainCancellation(): ?Cancellation
    {
        return $this->mainCancellation?->getCancellation();
    }
    
    public function awaitTermination(): void
    {
        if(false === $this->running) {
            return;
        }
        
        EventLoop::queue(function () {
            while ($this->running) {
                
                $futures            = [];
                
                foreach ($this->workers as $workerDescriptor) {
                    if($workerDescriptor->getFuture() !== null && false === $workerDescriptor->isStopped()) {
                        $futures[]  = $workerDescriptor->getFuture();
                    }
                }
                
                if(empty($futures)) {
                    
                    if(false === $this->mainCancellation->isCancelled()) {
                        $this->mainCancellation->cancel();
                    }
                    
                    break;
                }
                
                try {
                    Future\awaitAll($futures, $this->mainCancellation->getCancellation());
                } catch (CancelledException) {
                    break;
                }
            }
        });
        
        if(IS_WINDOWS) {
            $this->awaitWindowsEvents();
        } else {
            $this->awaitUnixEvents();
        }
    }
    
    public function scaleWorkers(int $groupId, int $count): int
    {
        if($count === 0) {
            return 0;
        }
        
        $group                      = $this->groupsScheme[$groupId] ?? null;
        
        if($group === null) {
            throw new \Error("The worker group with ID '{$groupId}' is not found");
        }

        $isDecrease                 = $count < 0;
        $count                      = \abs($count);
        $handled                    = 0;
        $stoppedWorkers             = [];
        
        foreach ($this->workers as $workerDescriptor) {
            if($workerDescriptor->group->getWorkerGroupId() !== $groupId) {
                continue;
            }
            
            // Skip stopped workers
            if($workerDescriptor->isStopped()) {
                continue;
            }
            
            if($handled >= $count) {
                break;
            }
            
            if($isDecrease && $workerDescriptor->shouldBeStarted === false && $workerDescriptor->getWorkerProcess() !== null) {
                $workerDescriptor->getWorkerProcess()->shutdownSoftly();
                $handled++;
                $stoppedWorkers[]   = $workerDescriptor->id;
            } elseif(false === $isDecrease && $workerDescriptor->getWorkerProcess() === null) {
                $this->startWorker($workerDescriptor);
                $handled++;
            }
        }
        
        $lowestWorkerId             = 0;
        $highestWorkerId            = 0;

        foreach ($this->workers as $workerDescriptor) {
            if($workerDescriptor->group->getWorkerGroupId() === $groupId && $workerDescriptor->getWorkerProcess() !== null) {
                if($lowestWorkerId === 0) {
                    $lowestWorkerId = $workerDescriptor->id;
                } else if(false === in_array($workerDescriptor->id, $stoppedWorkers, true)) {
                    $highestWorkerId = $workerDescriptor->id;
                }
            }
        }
        
        // Update state of the worker group
        $this->poolState->setWorkerGroupState($groupId, $lowestWorkerId, $highestWorkerId);
        
        return $handled;
    }
    
    public function getWorkerEventEmitter(): WorkerEventEmitterInterface
    {
        return $this->eventEmitter;
    }
    
    protected function awaitUnixEvents(): void
    {
        while ($this->mainCancellation !== null) {
            
            try {
                $signal             = trapSignal(
                    [\SIGINT, \SIGTERM, \SIGUSR1], true, $this->mainCancellation->getCancellation()
                );
            } catch (CancelledException) {
                break;
            }
            
            if($signal === \SIGINT || $signal === \SIGTERM) {
                $this->logger?->info('Server will stop due to signal SIGINT or SIGTERM');
                $this->stop();
                break;
            }
            
            if($signal === \SIGUSR1) {
                $this->logger?->info('Server should reload due to signal SIGUSR1');
                $this->restart();
            }
        }
    }
    
    protected function awaitWindowsEvents(): void
    {
        if($this->mainCancellation === null) {
            return;
        }
        
        $suspension             = EventLoop::getSuspension();
        $cancellation           = $this->mainCancellation->getCancellation();
        $id                     = $cancellation?->subscribe(static fn (CancelledException $exception) => $suspension->throw($exception));
        
        \sapi_windows_set_ctrl_handler(static function () use ($suspension) {
            $suspension->resume();
        });
        
        try {
            $suspension->suspend();
        } catch (CancelledException) {
            // Ignore
        } finally {
            /** @psalm-suppress PossiblyNullArgument $id will not be null if $cancellation is not null. */
            $cancellation?->unsubscribe($id);
        }
    }
    
    protected function applyGroupScheme(): void
    {
        foreach ($this->groupsScheme as $group) {
            $this->fillWorkersGroup($group);
        }
    }
    
    protected function startWorker(WorkerDescriptor $workerDescriptor): void
    {
        $runnerStrategy             = $workerDescriptor->group->getRunnerStrategy();
        
        if($runnerStrategy === null) {
            throw new \Error('The runner strategy is not defined');
        }
        
        $context                    = $this->contextFactory->start($runnerStrategy->getScript());
        $socketTransport            = null;
        
        try {
            $key                    = $runnerStrategy->sendPoolContext(
                $context,
                $workerDescriptor->id,
                $workerDescriptor->group
            );
            
            if($runnerStrategy->shouldProvideSocketTransport()) {
                $socketTransport    = $this->provider->createSocketTransport($key);
            }
        
        } catch (\Throwable $exception) {
            
            if (!$context->isClosed()) {
                $context->close();
            }
            
            throw new \Exception(
                "Starting the worker '{$workerDescriptor->id}' failed. Sending the pool context failed", previous: $exception
            );
        }
        
        $deferredCancellation       = new DeferredCancellation();
        
        $workerProcess              = new WorkerProcessContext(
            $workerDescriptor->id,
            $context,
            $socketTransport ?? $this->listenerProvider,
            $deferredCancellation,
            $this->eventEmitter,
        );
        
        if($this->logger !== null) {
            $workerProcess->setLogger($this->logger);
        }
        
        $workerDescriptor->setWorkerProcess($workerProcess);
        
        $workerProcess->info(\sprintf('Started %s worker #%d', $workerDescriptor->group->getWorkerType()->value, $workerDescriptor->id));
        
        // Server stopped while worker was starting, so immediately throw everything away.
        if (false === $this->running) {
            $workerProcess->shutdown();
            return;
        }
        
        $workerDescriptor->group->getRestartStrategy()?->onWorkerStart($workerDescriptor->id, $workerDescriptor->group);
        
        $workerDescriptor->setFuture(async($this->workerWatcher(...), $workerDescriptor, $deferredCancellation)->ignore());
    }
    
    /**
     * Watcher for the worker process and restarts it if necessary.
     *
     * @param WorkerDescriptor     $workerDescriptor
     * @param DeferredCancellation $deferredCancellation
     *
     * @return void
     * @throws ClusterException
     * @throws TaskFailureThrowable
     * @throws \Throwable
     */
    protected function workerWatcher(WorkerDescriptor $workerDescriptor, DeferredCancellation $deferredCancellation): void
    {
        if(false === $this->running) {
            return;
        }
        
        if($this->provider->used()) {
            async(
                $this->provider->provideFor(...),
                $workerDescriptor->getWorkerProcess()->getSocketTransport(),
                $deferredCancellation->getCancellation()
            )->ignore();
        }
        
        $id                         = $workerDescriptor->id;
        $workerProcess              = $workerDescriptor->getWorkerProcess();
        
        try {
            
            $exitResult             = $this->workerEventLoop($workerDescriptor, $deferredCancellation);
            
            $restarting             = $workerDescriptor->group->getRestartStrategy()?->shouldRestart($exitResult) ?? -1;
            
            // Restart the worker if the server is still running and the worker should be restarted.
            // We always terminate the worker if the server is not running
            // or $exitResult is an instance of TerminateWorkerException.
            if ($this->running && false === $exitResult instanceof TerminateWorkerException && $restarting >= 0) {
                
                if($restarting > 0) {
                    $workerProcess->info("Worker {$id} will be restarted in {$restarting} seconds");
                    EventLoop::delay($restarting, fn () => $this->startWorker($workerDescriptor));
                } else {
                    $this->startWorker($workerDescriptor);
                }
                
            } else if($restarting < 0) {
                
                $workerDescriptor->markAsStopped();
                
                $workerProcess->info(
                    "Worker {$id} will not be restarted: " .
                    $workerDescriptor->group->getRestartStrategy()?->getFailReason() ?? ''
                );
            }
            
        } catch (\Throwable $exception) {
            $this->stop();
            throw $exception;
        }
    }
    
    /**
     * Run the worker event loop and return the exit result.
     *
     * @param WorkerDescriptor     $workerDescriptor
     * @param DeferredCancellation $deferredCancellation
     *
     * @return mixed
     * @throws TaskFailureThrowable
     * @throws \Throwable
     */
    protected function workerEventLoop(WorkerDescriptor $workerDescriptor, DeferredCancellation $deferredCancellation): mixed
    {
        $id                         = $workerDescriptor->id;
        $workerProcess              = $workerDescriptor->getWorkerProcess();
        $exitResult                 = null;
        
        try {
            
            $workerProcess->runWorkerLoop();
            $workerProcess->info("Worker {$id} terminated cleanly");
            
        } catch (CancelledException $exception) {
            
            /**
             * The IPC socket has broken the connection,
             * and communication with the child process has been disrupted.
             * We interpret this as an abnormal termination of the worker.
             */
            $exitResult         = $exception;
            $workerProcess->info("Worker {$id} forcefully terminated");
            
        } catch (ChannelException $exception) {
            
            $exitResult         = $exception;
            
            $workerProcess->error(
                "Worker {$id} died unexpectedly: {$exception->getMessage()}" .
                ($this->running ? ", restarting..." : "")
            );
            
            $remoteException    = $exception->getPrevious();
            
            if (($remoteException instanceof TaskFailureThrowable
                 || $remoteException
                    instanceof
                    ContextPanicError)
                && $remoteException->getOriginalClassName() === FatalWorkerException::class) {
                
                // The Worker died due to a fatal error, so we should stop the server.
                $this->logger?->error('Server shutdown due to fatal worker error');
                throw $remoteException;
            }
        } catch (TerminateWorkerException $exception) {
            
            // The worker has terminated itself cleanly.
            $exitResult         = $exception;
            $workerProcess->info("Worker {$id} terminated cleanly without restart");
            
        } catch (\Throwable $exception) {
            
            $workerProcess->error("Worker {$id} failed: " . $exception, ['exception' => $exception]);
            throw $exception;
            
        } finally {
            
            if(false === $deferredCancellation->isCancelled()) {
                $deferredCancellation->cancel();
            }
            
            $workerDescriptor->reset();
            
            if (false === $workerProcess->getContext()->isClosed()) {
                $workerProcess->getContext()->close();
            }
        }
        
        return $exitResult;
    }
    
    protected function fillWorkersGroup(WorkerGroup $group): void
    {
        if($group->getWorkerGroupId() === 0) {
            throw new \Error('The group id must be greater than zero');
        }
        
        if($group->getMinWorkers() <= 0) {
            throw new \Error('The minimum number of workers must be greater than zero');
        }

        if($group->getMaxWorkers() < $group->getMinWorkers()) {
            throw new \Error('The maximum number of workers must be greater than or equal to the minimum number of workers');
        }
        
        $baseWorkerId               = $this->getLastWorkerId() + 1;
        
        // All workers in the group will have the same strategies
        $this->defaultWorkerStrategies($group);
        $this->initWorkerStrategies($group);
        
        foreach (range($baseWorkerId, $baseWorkerId + $group->getMinWorkers() - 1) as $id) {
            $this->addWorker(new WorkerDescriptor(
                $id, $group, $id <= ($baseWorkerId + $group->getMinWorkers() - 1
            )));
        }
    }
    
    protected function getLastWorkerId(): int
    {
        $maxId                      = 0;
        
        foreach ($this->workers as $worker) {
            if($worker->id > $maxId) {
                $maxId              = $worker->id;
            }
        }
        
        return $maxId;
    }
    
    protected function addWorker(WorkerDescriptor $worker): self
    {
        $this->workers[]            = $worker;
        return $this;
    }
    
    public function getWorkers(): array
    {
        $workers                    = [];
        
        foreach ($this->workers as $workerDescriptor) {
            $workers[]              = $workerDescriptor->id;
        }
        
        return $workers;
    }
    
    public function findWorkerContext(int $workerId): Context|null
    {
        foreach ($this->workers as $workerDescriptor) {
            if($workerDescriptor->id === $workerId) {
                return $workerDescriptor->getWorkerProcess()->getContext();
            }
        }
        
        return null;
    }
    
    /**
     * Stops all server workers. Workers are killed if the cancellation token is canceled.
     *
     * @param Cancellation|null $cancellation Token to request cancellation of waiting for shutdown.
     *                                        When canceled, the workers are forcefully killed. If null, the workers
     *                                        are killed immediately.
     *
     * @throws ClusterException
     */
    public function stop(?Cancellation $cancellation = null): void
    {
        if (false === $this->running) {
            return;
        }
        
        $this->running              = false;
        
        $cancellation               ??= new TimeoutCancellation($this->workerStopTimeout);
        $this->listenerProvider?->close();
        
        $exceptions                 = $this->stopWorkers($cancellation);
        
        $this->mainCancellation?->cancel();
        $this->mainCancellation     = null;
        
        if (!$exceptions) {
            return;
        }
        
        if (\count($exceptions) === 1) {
            $exception              = \array_shift($exceptions);
            
            throw new WorkerPoolException(
                'Stopping the server failed: ' . $exception->getMessage(),
                previous: $exception,
            );
        }
        
        $exception              = new CompositeException($exceptions);
        $message                = \implode('; ', \array_map(static fn (\Throwable $e) => $e->getMessage(), $exceptions));
        
        throw new WorkerPoolException('Stopping the server failed: ' . $message, previous: $exception);
    }
    
    public function restart(?Cancellation $cancellation = null): void
    {
        $this->stop($cancellation);
        
        if(PHP_OS_FAMILY === 'Windows') {
            $this->listenerProvider = new SocketListenerProvider($this);
        }
        
        $this->run();
        
        $this->logger?->info('Server reloaded');
    }
    
    public function countWorkers(int $groupId, bool $onlyRunning = false, bool $notRunning = false): int
    {
        $count                      = 0;
        
        foreach ($this->workers as $worker) {
            if($worker->group->getWorkerGroupId() !== $groupId) {
                continue;
            }
            
            if($onlyRunning && $worker->getWorkerProcess() !== null) {
                $count++;
            } elseif ($notRunning && $worker->getWorkerProcess() === null) {
                $count++;
            } else {
                $count++;
            }
        }
        
        return $count;
    }
    
    public function __destruct()
    {
        EventLoop::queue($this->stop(...));
    }
    
    protected function defaultWorkerStrategies(WorkerGroup $group): void
    {
        if($group->getRunnerStrategy() === null) {
            $group->defineRunnerStrategy(new DefaultRunner);
        }
        
        if($group->getPickupStrategy() === null) {
            $group->definePickupStrategy(new PickupLeastJobs);
        }
        
        if($group->getScalingStrategy() === null) {
            $group->defineScalingStrategy(new ScalingByRequest);
        }
        
        if($group->getRestartStrategy() === null) {
            $group->defineRestartStrategy(new RestartAlways);
        }
    }
    
    protected function initWorkerStrategies(WorkerGroup $group): void
    {
        $strategy                   = $group->getRunnerStrategy();
        
        if($strategy instanceof WorkerStrategyInterface) {
            $strategy->setWorkerPool($this)->setWorkerGroup($group);
        }
        
        $strategy                   = $group->getPickupStrategy();
        
        if($strategy instanceof WorkerStrategyInterface) {
            $strategy->setWorkerPool($this)->setWorkerGroup($group);
        }
        
        $strategy                   = $group->getScalingStrategy();
        
        if($strategy instanceof WorkerStrategyInterface) {
            $strategy->setWorkerPool($this)->setWorkerGroup($group);
        }
        
        $strategy                   = $group->getRestartStrategy();
        
        if($strategy instanceof WorkerStrategyInterface) {
            $strategy->setWorkerPool($this)->setWorkerGroup($group);
        }
    }
    
    protected function stopWorkers(Cancellation $cancellation): array
    {
        $futures                    = [];
        
        foreach ($this->workers as $workerDescriptor) {
            $futures[]              = async(static function () use ($workerDescriptor, $cancellation): void {
                
                $future             = $workerDescriptor->getFuture();
                
                try {
                    $workerDescriptor->getWorkerProcess()?->shutdown($cancellation);
                } catch (ContextException) {
                    // Ignore if the worker has already died unexpectedly.
                }

                try {
                    // We need to await this future here, otherwise we may not log things properly if the
                    // event-loop exits immediately after.
                    $future?->await($cancellation);
                } catch (CancelledException) {
                    $this->logger?->error('Worker did not die normally within a cancellation window');
                }
            });
        }
        
        [$exceptions]               = Future\awaitAll($futures);
        
        $this->workers              = [];
        
        return $exceptions;
    }
    
    protected function updateGroupsState(): void
    {
        $groupsState                = [];
        
        foreach ($this->workers as $workerDescriptor) {
            if($workerDescriptor->getWorkerProcess() === null) {
                continue;
            }
            
            $group                  = $workerDescriptor->group;
            $groupId                = $group->getWorkerGroupId();
            
            if(false === array_key_exists($groupId, $groupsState)) {
                $groupsState[$groupId] = [$workerDescriptor->id, $workerDescriptor->id];
            } elseif ($groupsState[$groupId][1] < $workerDescriptor->id) {
                $groupsState[$groupId][1] = $workerDescriptor->id;
            }
        }
        
        $this->poolState->setGroupsState($groupsState);
    }
}