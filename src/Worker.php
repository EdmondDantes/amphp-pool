<?php
declare(strict_types=1);

namespace CT\AmpServer;

use Amp\Cancellation;
use Amp\Cluster\ServerSocketPipeFactory;
use Amp\DeferredCancellation;
use Amp\DeferredFuture;
use Amp\Parallel\Ipc;
use Amp\Pipeline\ConcurrentIterator;
use Amp\Pipeline\Queue;
use Amp\Socket\ResourceSocket;
use Amp\Socket\ServerSocketFactory;
use Amp\Sync\Channel;
use Amp\TimeoutCancellation;
use CT\AmpServer\Messages\MessagePingPong;
use CT\AmpServer\SocketPipe\SocketPipeFactoryWindows;
use Psr\Log\LoggerInterface;
use Amp\Parallel\Ipc\IpcHub;
use Revolt\EventLoop;

/**
 * Abstraction of Worker Representation within the worker process.
 * This class should not be used within the process that creates workers!
 *
 * @template-covariant TReceive
 * @template TSend
 * @implements Channel<TReceive, TSend>
 */
class Worker
{
    protected int $timeout = 5;
    
    protected readonly DeferredCancellation $loopCancellation;
    
    /** @var Queue<TReceive> */
    protected readonly Queue $queue;
    
    /** @var ConcurrentIterator<TReceive> */
    protected readonly ConcurrentIterator $iterator;
    
    protected ?ResourceSocket $ipcForTransferSocket = null;
    protected ?ServerSocketFactory $socketPipeFactory = null;
    
    private LoggerInterface $logger;
    private array                $messageHandlers = [];
    private WorkerIpcServer|null $jobIpc          = null;
    
    public function __construct(
        private readonly int     $id,
        private readonly Channel $ipcChannel,
        private readonly string  $key,
        private readonly string  $uri,
        private readonly string  $workerType,
        LoggerInterface          $logger = null
    ) {
        $this->queue                = new Queue();
        $this->iterator             = $this->queue->iterate();
        $this->loopCancellation     = new DeferredCancellation();
        
        if($this->workerType === WorkerTypeEnum::JOB->value) {
            $this->jobIpc           = new WorkerIpcServer($this->id);
        }
        
        if($logger !== null) {
            $this->logger           = $logger;
        } else {
            $this->logger           = new \Monolog\Logger('worker-'.$id);
            $this->logger->pushHandler(new WorkerLogHandler($this->ipcChannel));
        }
    }
    
    public function initWorker(): void
    {
        $this->getSocketPipeFactory();
    }
    
    public function getLogger(): LoggerInterface
    {
        return $this->logger;
    }
    
    public function getWorkerId(): int
    {
        return $this->id;
    }
    
    public function getWorkerType(): string
    {
        return $this->workerType;
    }
    
    public function getIpcForTransferSocket(): ResourceSocket
    {
        if($this->ipcForTransferSocket !== null) {
            return $this->ipcForTransferSocket;
        }
        
        try {
            $socket                 = Ipc\connect($this->uri, $this->key, new TimeoutCancellation($this->timeout));
            
            if($socket instanceof ResourceSocket) {
                $this->ipcForTransferSocket = $socket;
            } else {
                throw new \RuntimeException('Type of socket is not ResourceSocket');
            }
            
        } catch (\Throwable $exception) {
            throw new \RuntimeException('Could not connect to IPC socket', 0, $exception);
        }
        
        return $this->ipcForTransferSocket;
    }
    
    public function getSocketPipeFactory(): ServerSocketFactory
    {
        if (PHP_OS_FAMILY === 'Windows') {
            return new SocketPipeFactoryWindows($this->ipcChannel, $this);
        }
        
        if($this->socketPipeFactory !== null) {
            return $this->socketPipeFactory;
        }
        
        $this->socketPipeFactory    = new ServerSocketPipeFactory($this->getIpcForTransferSocket());
        
        return $this->socketPipeFactory;
    }
    
    public function mainLoop(): void
    {
        $abortCancellation          = $this->loopCancellation->getCancellation();
        
        if($this->workerType === WorkerTypeEnum::JOB->value) {
            EventLoop::queue($this->jobLoop(...), $abortCancellation);
        }
        
        try {
            while ($message = $this->ipcChannel->receive($abortCancellation)) {
                
                if($message instanceof MessagePingPong) {
                    $this->ipcChannel->send(new MessagePingPong);
                    continue;
                }
                
                try {
                    foreach ($this->messageHandlers as $eventHandler) {
                        if($eventHandler($message)) {
                            break;
                        }
                    }
                } catch (\Throwable $exception) {
                    $this->logger->error('Error processing message', ['exception' => $exception]);
                }
            }
        } catch (\Throwable) {
            // IPC Channel manually closed
        } finally {
            $this->messageHandlers = [];
            $this->loopCancellation->cancel();
            $this->queue->complete();
            $this->ipcForTransferSocket?->close();
        }
    }
    
    protected function jobLoop(Cancellation $cancellation = null): void
    {
        while ()
    }
    
    public function addEventHandler(callable $handler): self
    {
        $this->messageHandlers[] = $handler;
        
        return $this;
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
        }
    }
    
    public function __destruct()
    {
        $this->close();
    }
    
    public function send(mixed $data): void
    {
        // TODO: Implement send() method.
        //$this->ipcChannel->send(new WorkerMessage(WorkerMessageType::DATA, $data));
    }
    
    public function close(): void
    {
        $this->loopCancellation->cancel();
    }
    
    public function isClosed(): bool
    {
        return $this->loopCancellation->isCancelled();
    }
    
    public function onClose(\Closure $onClose): void
    {
        $this->loopCancellation->getCancellation()->subscribe(static fn () => $onClose());
    }
    
    public function __toString(): string
    {
        return 'worker-'.$this->id;
    }
}