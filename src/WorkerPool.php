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
use CT\AmpPool\PoolState\PoolStateStorage;
use CT\AmpPool\PoolState\PoolStateReadableInterface;
use CT\AmpPool\SocketPipe\SocketListenerProvider;
use CT\AmpPool\SocketPipe\SocketPipeProvider;
use CT\AmpPool\Worker\PickupStrategy\PickupLeastJobs;
use CT\AmpPool\Worker\RestartStrategy\RestartAlways;
use CT\AmpPool\Worker\RunnerStrategy\DefaultRunner;
use CT\AmpPool\Worker\ScalingStrategy\ScalingByRequest;
use CT\AmpPool\Worker\WorkerDescriptor;
use CT\AmpPool\Worker\WorkerState\WorkersInfo;
use CT\AmpPool\Worker\WorkerState\WorkersInfoInterface;
use CT\AmpPool\Worker\WorkerStrategyInterface;
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
        
        $this->script               = \array_merge(
            [__DIR__ . '/runner.php'],
            \is_array($script) ? \array_values(\array_map(\strval(...), $script)) : [$script],
        );
        
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
        
        if($group->getMaxWorkers() < $group->getMinWorkers()) {
            throw new \Error('The maximum number of workers must be greater than or equal to the minimum number of workers');
        }
        
        if($group->getMaxWorkers() === 0) {
            $group->defineMaxWorkers($group->getMinWorkers());
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
        $this->mainCancellation     = new DeferredCancellation();

        try {
            
            if($this->poolState === null) {
                $this->poolState    = new PoolStateStorage(count($this->groupsScheme));
                $this->poolState->setGroupsState($this->groupsScheme);
            }
            
            foreach ($this->workers as $worker) {
                if($worker->shouldBeStarted) {
                    $this->startWorker($worker);
                }
            }
        } catch (\Throwable $exception) {
            $this->stop();
            throw $exception;
        }
    }
    
    public function awaitTermination(): void
    {
        if(IS_WINDOWS) {
            $this->awaitUnixEvents();
        } else {
            $this->awaitWindowsEvents();
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
        
        foreach ($this->workers as $worker) {
            if($worker->group->getWorkerGroupId() !== $groupId) {
                continue;
            }
            
            if($handled >= $count) {
                break;
            }
            
            if($isDecrease && $worker->shouldBeStarted === false && $worker->getWorker() !== null) {
                $worker->getWorker()->shutdownSoftly();
                $handled++;
                $stoppedWorkers[]   = $worker->id;
            } elseif(false === $isDecrease && $worker->getWorker() === null) {
                $this->startWorker($worker);
                $handled++;
            }
        }
        
        $lowestWorkerId             = 0;
        $highestWorkerId            = 0;

        foreach ($this->workers as $worker) {
            if($worker->group->getWorkerGroupId() === $groupId && $worker->getWorker() !== null) {
                if($lowestWorkerId === 0) {
                    $lowestWorkerId = $worker->id;
                } else if(false === in_array($worker->id, $stoppedWorkers, true)) {
                    $highestWorkerId = $worker->id;
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
        while (true) {
            
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
        $suspension             = EventLoop::getSuspension();
        $cancellation           = $this->mainCancellation->getCancellation();
        $id                     = $cancellation?->subscribe(static fn (CancelledException $exception) => $suspension->throw($exception));
        
        sapi_windows_set_ctrl_handler(static function () use ($suspension) {
            $suspension->resume();
        });
        
        try {
            $suspension->suspend();
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
    
    private function startWorker(WorkerDescriptor $workerDescriptor): void
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
        
        $worker                     = new WorkerProcessContext(
            $workerDescriptor->id,
            $context,
            $socketTransport ?? $this->listenerProvider,
            $deferredCancellation,
            $this->eventEmitter,
        );
        
        if($this->logger !== null) {
            $worker->setLogger($this->logger);
        }
        
        $workerDescriptor->setWorker($worker);
        
        $worker->info(\sprintf('Started %s worker #%d', $workerDescriptor->group->getWorkerType()->value, $workerDescriptor->id));
        
        // Server stopped while worker was starting, so immediately throw everything away.
        if (false === $this->running) {
            $worker->shutdown();
            return;
        }
        
        $workerDescriptor->group->getRestartStrategy()->onWorkerStart($workerDescriptor->id, $workerDescriptor->group);
        
        $workerDescriptor->setFuture(async(function () use (
            $worker,
            $context,
            $socketTransport,
            $deferredCancellation,
            $workerDescriptor
        ): void {
            async($this->provider->provideFor(...), $socketTransport, $deferredCancellation->getCancellation())->ignore();
            
            $id                         = $workerDescriptor->id;
            $exitResult                 = null;
            
            try {
                try {
                    $worker->runWorkerLoop();
                    
                    $restarting         = $workerDescriptor->group->getRestartStrategy()->shouldRestart($exitResult);
                    
                    $worker->info("Worker {$id} terminated cleanly" . ($restarting >= 0 ? ", restarting..." : ""));
                    
                } catch (CancelledException $exception) {
                    
                    /**
                     * The IPC socket has broken the connection,
                     * and communication with the child process has been disrupted.
                     * We interpret this as an abnormal termination of the worker.
                     */
                    $exitResult         = $exception;
                    $worker->info("Worker {$id} forcefully terminated");
                    
                } catch (ChannelException $exception) {
                    $worker->error(
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
                    $worker->info("Worker {$id} terminated cleanly without restart");
                    
                } catch (\Throwable $exception) {
                    
                    $worker->error("Worker {$id} failed: " . $exception, ['exception' => $exception]);
                    throw $exception;
                    
                } finally {
                    
                    if(!$deferredCancellation->isCancelled()) {
                        $deferredCancellation->cancel();
                    }
                    
                    $workerDescriptor->reset();
                    
                    if (!$context->isClosed()) {
                        $context->close();
                    }
                }
                
                $restarting         = $workerDescriptor->group->getRestartStrategy()->shouldRestart($exitResult);
                
                // Restart the worker if the server is still running and the worker should be restarted.
                // We always terminate the worker if the server is not running
                // or $exitResult is an instance of TerminateWorkerException.
                if ($this->running && false === $exitResult instanceof TerminateWorkerException && $restarting >= 0) {
                    
                    if($restarting > 0) {
                        $worker->info("Worker {$id} will be restarted in {$restarting} seconds");
                        EventLoop::delay($restarting, fn () => $this->startWorker($workerDescriptor));
                    } else {
                        $this->startWorker($workerDescriptor);
                    }
                    
                } else if($restarting < 0) {
                    $worker->info(
                        "Worker {$id} will not be restarted: " .
                                  $workerDescriptor->group->getRestartStrategy()->getFailReason()
                    );
                }
                
            } catch (\Throwable $exception) {
                $this->stop();
                throw $exception;
            }
        })->ignore());
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
        
        if (!$exceptions) {
            return;
        }
        
        if (\count($exceptions) === 1) {
            $exception              = \array_shift($exceptions);
            
            throw new ClusterException(
                'Stopping the server failed: ' . $exception->getMessage(),
                previous: $exception,
            );
        }
        
        $exception              = new CompositeException($exceptions);
        $message                = \implode('; ', \array_map(static fn (\Throwable $e) => $e->getMessage(), $exceptions));
        
        throw new ClusterException('Stopping the server failed: ' . $message, previous: $exception);
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
            
            if($onlyRunning && $worker->getWorker() !== null) {
                $count++;
            } elseif ($notRunning && $worker->getWorker() === null) {
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
                    $workerDescriptor->getWorker()?->shutdown($cancellation);
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
}