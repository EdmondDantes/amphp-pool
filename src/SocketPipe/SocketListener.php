<?php
declare(strict_types=1);

namespace CT\AmpServer\SocketPipe;

use Amp\Parallel\Context\ProcessContext;
use Amp\Socket\BindContext;
use Amp\Socket\ResourceServerSocket;
use Amp\Socket\ServerSocket;
use Amp\Socket\SocketAddress;
use CT\AmpServer\Messages\MessageSocketTransfer;
use CT\AmpServer\PickupWorkerStrategy\PickupWorkerRoundRobin;
use CT\AmpServer\PickupWorkerStrategy\PickupWorkerStrategyInterface;
use CT\AmpServer\WorkerPool;
use Revolt\EventLoop;

/**
 * The class listens to the specified address
 * and calls a callback method to transmit the socket when a new packet is received
 */
final class SocketListener
{
    private array                         $workers          = [];
    private PickupWorkerStrategyInterface $pickupWorkerStrategy;
    
    public function __construct(
        private readonly SocketAddress $address,
        private readonly WorkerPool $workerPool,
        PickupWorkerStrategyInterface $pickupWorkerStrategy = null
    )
    {
        $this->pickupWorkerStrategy = $pickupWorkerStrategy ?? new PickupWorkerRoundRobin($this->workerPool);
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
    
    public function receiveLoop(): void
    {
        $server                     = $this->listenAddress($this->address);
        
        EventLoop::queue(function () use ($server) {
            while ($socket = $server->accept()) {
                // Select free worker
                $foundedWorker              = $this->pickupWorkerStrategy->pickupWorker(possibleWorkers: $this->workers)?->getWorker();
                
                if ($foundedWorker === null) {
                    $socket->close();
                    return;
                }
                
                $pid                        = 0;
                
                if($foundedWorker->getContext() instanceof ProcessContext) {
                    $pid                    = $foundedWorker->getContext()->getPid();
                }
                
                $socketId                   = \socket_wsaprotocol_info_export(\socket_import_stream($socket->getResource()), $pid);
                
                if(false === $socketId) {
                    $socket->close();
                    throw new \Exception('Failed to export socket information');
                }
                
                try {
                    $foundedWorker->getContext()->send(new MessageSocketTransfer($socketId));
                    $foundedWorker->addTransferredSocket($socketId, $socket);
                } catch (\Throwable $exception) {
                    $socket->close();
                    throw $exception;
                }
            }
        });
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
}