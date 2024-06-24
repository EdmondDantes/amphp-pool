<?php
declare(strict_types=1);

namespace CT\AmpPool\Strategies\SocketStrategy\Unix;

use Amp\Cluster\ServerSocketPipeFactory;
use Amp\DeferredFuture;
use Amp\Socket\ResourceSocket;
use Amp\Socket\ServerSocketFactory;
use Amp\TimeoutCancellation;
use CT\AmpPool\EventWeakHandler;
use CT\AmpPool\Strategies\SocketStrategy\SocketStrategyInterface;
use CT\AmpPool\Strategies\SocketStrategy\Unix\Messages\InitiateSocketTransfer;
use CT\AmpPool\Strategies\SocketStrategy\Unix\Messages\SocketTransferInfo;
use CT\AmpPool\Strategies\WorkerStrategyAbstract;
use Amp\Parallel\Ipc;
use CT\AmpPool\WatcherEvents\WorkerProcessStarted;
use function Amp\Future\await;

final class SocketUnixStrategy      extends WorkerStrategyAbstract
                                    implements SocketStrategyInterface
{
    private ServerSocketPipeFactory|null $socketPipeFactory = null;
    private string              $uri                = '';
    private string              $key                = '';
    private DeferredFuture|null $deferredFuture     = null;
    private EventWeakHandler|null $workerEventHandler = null;
    
    /** @var SocketProvider[] */
    private array $workerSocketProviders = [];
    
    public function __construct(private readonly int $ipcTimeout = 5) {}
    
    public function onStarted(): void
    {
        $workerPool                 = $this->getWorkerPool();
        
        if($workerPool !== null) {
            
            $self                   = \WeakReference::create($this);
            
            $workerPool->getWorkerEventEmitter()
                       ->addWorkerEventListener(static function (mixed $message, int $workerId = 0) use($self) {
                $self->get()?->handleMessage($message, $workerId);
            });
            
            return;
        }
        
        $worker                     = $this->getWorker();
        
        if($worker === null) {
            return;
        }
        
        $this->deferredFuture       = new DeferredFuture;
        
        $self                       = \WeakReference::create($this);
        $this->workerEventHandler   = new EventWeakHandler(
            $this,
            static function (mixed $message, int $workerId = 0) use($self) {
                $self->get()?->handleMessage($message, $workerId);
            }
        );
        
        $worker->getWorkerEventEmitter()->addWorkerEventListener($this->workerEventHandler);
        
        $worker->sendMessageToWatcher(
            new InitiateSocketTransfer($worker->getWorkerId(), $worker->getWorkerGroup()->getWorkerGroupId())
        );
    }
    
    public function onStopped(): void
    {
        if(false === $this->deferredFuture?->isComplete()) {
            $this->deferredFuture->complete();
            $this->deferredFuture   = null;
        }

        if($this->workerEventHandler !== null) {
            $this->getWorker()?->getWorkerEventEmitter()->removeWorkerEventListener($this->workerEventHandler);
            $this->workerEventHandler = null;
        }

        $this->socketPipeFactory    = null;

        $providers                  = $this->workerSocketProviders;
        $this->workerSocketProviders = [];
        
        foreach ($providers as $socketProvider) {
            $socketProvider->stop();
        }
    }
    
    /**
     * Calling this method pauses the Worker’s execution thread until the Watcher returns data for socket initialization.
     *
     * @return ServerSocketFactory|null
     */
    public function getServerSocketFactory(): ServerSocketFactory|null
    {
        if($this->socketPipeFactory !== null) {
            return $this->socketPipeFactory;
        }
        
        if($this->deferredFuture === null) {
            throw new \Error('Wrong usage of the method getServerSocketFactory(). The deferredFuture undefined.');
        }
        
        await([$this->deferredFuture->getFuture()], new TimeoutCancellation($this->ipcTimeout));
        
        return $this->socketPipeFactory;
    }
    
    private function createIpcForTransferSocket(): ResourceSocket
    {
        $worker                     = $this->getWorker();
        
        if($worker === null) {
            throw new \Error('Wrong usage of the method getServerSocketFactory(). This method can be used only inside the worker!');
        }
        
        try {
            $socket                 = Ipc\connect($this->uri, $this->key, new TimeoutCancellation($this->ipcTimeout));
            
            if($socket instanceof ResourceSocket) {
                return $socket;
            } else {
                throw new \RuntimeException('Type of socket is not ResourceSocket');
            }
            
        } catch (\Throwable $exception) {
            throw new \RuntimeException('Could not connect to IPC socket', 0, $exception);
        }
    }
    
    private function handleMessage(mixed $message, int $workerId = 0): void
    {
        if($message instanceof SocketTransferInfo) {
            
            if($this->workerEventHandler !== null) {
                $this->getWorker()?->getWorkerEventEmitter()->removeWorkerEventListener($this->workerEventHandler);
                $this->workerEventHandler = null;
            }
            
            if($this->deferredFuture === null || $this->deferredFuture->isComplete()) {
                return;
            }
            
            $this->uri              = $message->uri;
            $this->key              = $message->key;
            
            $this->socketPipeFactory = new ServerSocketPipeFactory($this->createIpcForTransferSocket());
            $this->deferredFuture->complete();
            
            return;
        }
        
        $workerPool             = $this->getWorkerPool();
        
        if($workerPool === null) {
            return;
        }
        
        //
        // Events from the watcher
        //
        
        if($message instanceof InitiateSocketTransfer) {
            
            if($message->groupId !== $this->getWorkerGroup()?->getWorkerGroupId()) {
                return;
            }
            
            $workerContext              = $workerPool->findWorkerContext($message->workerId);
            
            if($workerContext === null) {
                return;
            }
            
            $workerCancellation         = $workerPool->findWorkerCancellation($message->workerId);
            
            try {
                
                $ipcHub                 = $workerPool->getIpcHub();
                $ipcKey                 = $ipcHub->generateKey();
                $socketPipeProvider     = new SocketProvider($ipcHub, $ipcKey, $workerCancellation, $this->ipcTimeout);
                
                $workerContext->send(new SocketTransferInfo($ipcKey, $ipcHub->getUri()));
                
                if(array_key_exists($message->workerId, $this->workerSocketProviders)) {
                    $this->workerSocketProviders[$message->workerId]->stop();
                }
                
                $this->workerSocketProviders[$message->workerId] = $socketPipeProvider;
                
                $socketPipeProvider->start();
                
            } catch (\Throwable $exception) {
                $workerPool->getLogger()?->error('Could not send socket transfer info to worker', ['exception' => $exception]);
            }
        }
    }

    public function __serialize(): array
    {
        return ['ipcTimeout' => $this->ipcTimeout];
    }

    public function __unserialize(array $data): void
    {
        $this->ipcTimeout           = $data['ipcTimeout'] ?? 5;
    }
}