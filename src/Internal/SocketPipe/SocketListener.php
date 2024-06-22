<?php
declare(strict_types=1);

namespace CT\AmpPool\Internal\SocketPipe;

use Amp\Cancellation;
use Amp\Socket\BindContext;
use Amp\Socket\ResourceServerSocket;
use Amp\Socket\ResourceSocket;
use Amp\Socket\ServerSocket;
use Amp\Socket\Socket;
use Amp\Socket\SocketAddress;
use CT\AmpPool\Internal\Messages\MessageReady;
use CT\AmpPool\Internal\Messages\MessageSocketFree;
use CT\AmpPool\Internal\Messages\MessageSocketTransfer;
use CT\AmpPool\WorkerEventEmitterAwareInterface;
use CT\AmpPool\WorkerPoolInterface;
use Revolt\EventLoop;

/**
 * The class listens to the specified address
 * and calls a callback method to transmit the socket when a new packet is received
 */
final class SocketListener
{
    private array                   $workers          = [];
    private array                   $transferredSockets = [];
    private array                   $requestsByWorker   = [];
    private Cancellation|null       $cancellation       = null;
    
    public function __construct(
        private readonly SocketAddress $address,
        private readonly WorkerPoolInterface $workerPool
    )
    {
        $this->cancellation         = $this->workerPool->getMainCancellation();
        
        if($this->workerPool instanceof WorkerEventEmitterAwareInterface) {
            $this->workerPool->getWorkerEventEmitter()->addWorkerEventListener($this->eventListener(...));
        }
    }
    
    public function getSocketAddress(): SocketAddress
    {
        return $this->address;
    }
    
    public function getWorkers(): array
    {
        return $this->workers;
    }
    
    public function addWorker(int $workerId): self
    {
        if(in_array($workerId, $this->workers)) {
            return $this;
        }
        
        $this->workers[]            = $workerId;
        return $this;
    }
    
    public function startListen(): void
    {
        EventLoop::queue($this->receiveLoop(...), $this->listenAddress($this->address));
    }
    
    private function receiveLoop(ServerSocket $server): void
    {
        while (($socket = $server->accept($this->cancellation)) !== null) {
            
            // Select free worker
            $foundedWorkerId            = $this->pickupWorker();
            
            if ($foundedWorkerId === null) {
                $socket->close();
                return;
            }
            
            $foundedWorker              = $this->workerPool->findWorkerContext($foundedWorkerId);
            
            if($foundedWorker === null) {
                $socket->close();
                return;
            }
            
            $pid                        = $foundedWorker->getPid();
            
            $socketId                   = \socket_wsaprotocol_info_export(\socket_import_stream($socket->getResource()), $pid);
            
            if(false === $socketId) {
                $socket->close();
                throw new \Exception('Failed to export socket information');
            }
            
            try {
                $foundedWorker->send(new MessageSocketTransfer($socketId));
                $this->addTransferredSocket($foundedWorkerId, $socketId, $socket);
            } catch (\Throwable $exception) {
                $socket->close();
                throw $exception;
            }
        }
    }
    
    private function listenAddress(SocketAddress $address, ?BindContext $bindContext = null): ServerSocket
    {
        $bindContext                = $bindContext ?? new BindContext();
        
        return new ResourceServerSocket(
            $this->bind((string) $address, $bindContext), $bindContext
        );
    }
    
    private function bind(string $uri, BindContext $bindContext)
    {
        static $errorHandler;
        
        $context                    = \stream_context_create(\array_merge(
             $bindContext->toStreamContextArray(),
             [
                 'socket'           => [
                     'so_reuseaddr' => \PHP_OS_FAMILY === 'Windows',
                     'ipv6_v6only'  => true,
                 ],
             ],
         ));
        
        // Error reporting suppressed as stream_socket_server() error is immediately checked and
        // reported with an exception.
        \set_error_handler($errorHandler ??= static fn () => true);
        
        try {
            // Do NOT use STREAM_SERVER_LISTEN here - we explicitly invoke \socket_listen() in our worker processes
            if (!$server = \stream_socket_server($uri, $errno, $errstr, STREAM_SERVER_BIND | STREAM_SERVER_LISTEN, $context)) {
                throw new \RuntimeException(\sprintf(
                                                'Failed binding socket on %s: [Err# %s] %s',
                                                $uri,
                                                $errno,
                                                $errstr,
                                            ));
            }
        } finally {
            \restore_error_handler();
        }
        
        return $server;
    }
    
    private function pickupWorker(): int|null
    {
    
    }
    
    private function eventListener(mixed $event, int $workerId = 0): void
    {
        if($event instanceof MessageReady) {
            $this->isReady = true;
        } elseif($event instanceof MessageSocketTransfer) {
            $this->isReady  = true;
        } elseif($event instanceof MessageSocketFree) {
            $this->isReady  = true;
            $this->freeTransferredSocket($workerId, $event->socketId);
        }
    }
    
    private function addTransferredSocket(int $workerId, string $socketId, ResourceSocket|Socket $socket): void
    {
        $this->transferredSockets[$socketId] = $socket;
        $this->requestsByWorker[$workerId]   = ($this->requestsByWorker[$workerId] ?? 0) + 1;
    }
    
    private function freeTransferredSocket(int $workerId, string $socketId = null): void
    {
        if($socketId === null) {
            return;
        }
        
        if(array_key_exists($socketId, $this->transferredSockets)) {
            $this->transferredSockets[$socketId]->close();
            unset($this->transferredSockets[$socketId]);
        }

        if(array_key_exists($workerId, $this->requestsByWorker)) {
            $this->requestsByWorker[$workerId]--;
            
            if($this->requestsByWorker[$workerId] < 0) {
                $this->requestsByWorker[$workerId] = 0;
            }
        }
    }
    
}