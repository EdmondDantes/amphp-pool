<?php
declare(strict_types=1);

namespace CT\AmpServer\SocketPipe;

use Amp\Socket\SocketAddress;

final class SocketListenerProvider
{
    /** @var array<string, SocketListener> */
    private array $sockets          = [];
    
    private mixed $receiveCallback  = null;
    
    public function __construct(callable $receiveCallback)
    {
        $this->receiveCallback      = \WeakReference::create($receiveCallback);
    }
    
    public function listen(int $workerId, SocketAddress|string $address): void
    {
        if (false === $address instanceof SocketAddress) {
            // Normalize to SocketAddress here to avoid throwing exception for invalid strings at a receiving end.
            $address                = SocketAddress\fromString($address);
        }
        
        $receiveCallback            = $this->receiveCallback->get();
        
        if(null === $receiveCallback) {
            return;
        }
        
        $stringAddress              = (string) $address;

        if(array_key_exists($stringAddress, $this->sockets)) {
            $this->sockets[$stringAddress]->addWorker($workerId);
            return;
        }
        
        $this->sockets[$stringAddress] = new SocketListener($address, $this->receiveCallback->get());
        $this->sockets[$stringAddress]->addWorker($workerId);
        $this->sockets[$stringAddress]->receiveLoop();
    }
    
    public function close(): void
    {
        $this->sockets              = [];
    }
}