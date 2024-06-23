<?php
declare(strict_types=1);

namespace CT\AmpPool\Strategies\SocketStrategy\Windows;

use Amp\Socket\SocketAddress;
use CT\AmpPool\EventWeakHandler;
use CT\AmpPool\Strategies\SocketStrategy\Windows\Messages\MessageSocketListen;
use CT\AmpPool\WorkerGroupInterface;
use CT\AmpPool\WorkerPool;

final class SocketListenerProvider
{
    /** @var array<string, SocketClientListenerProvider> */
    private array $socketListeners  = [];
    private mixed $eventListener;
    
    public function __construct(private readonly WorkerPool $workerPool, private readonly WorkerGroupInterface $workerGroup)
    {
        $self                   = \WeakReference::create($this);
        
        $this->eventListener    = new EventWeakHandler($this,
            static function (mixed $event, int $workerId = 0) use($self) {
                $self->get()?->eventListener($event, $workerId);
            });
        
        $this->workerPool->getWorkerEventEmitter()->addWorkerEventListener($this->eventListener);
    }
    
    private function listen(int $workerId, SocketAddress|string $address): void
    {
        if (false === $address instanceof SocketAddress) {
            // Normalize to SocketAddress here to avoid throwing exception for invalid strings at a receiving end.
            $address                = SocketAddress\fromString($address);
        }
        
        $stringAddress              = (string) $address;

        if(array_key_exists($stringAddress, $this->socketListeners)) {
            $this->socketListeners[$stringAddress]->addWorker($workerId);
            return;
        }
        
        $this->socketListeners[$stringAddress] = new SocketClientListenerProvider($address, $this->workerPool, $this->workerGroup);
        $this->socketListeners[$stringAddress]->addWorker($workerId);
        $this->socketListeners[$stringAddress]->startListen();
    }
    
    public function close(): void
    {
        $this->socketListeners = [];
        
        if($this->eventListener !== null) {
            $this->workerPool->getWorkerEventEmitter()->removeWorkerEventListener($this->eventListener);
            $this->eventListener = null;
        }
    }
    
    private function eventListener(mixed $event, int $workerId = 0): void
    {
        if ($event instanceof MessageSocketListen) {
            $this->listen($workerId, $event->address);
        }
    }
}