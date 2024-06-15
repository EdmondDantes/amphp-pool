<?php
declare(strict_types=1);

namespace CT\AmpPool\SocketPipe;

use Amp\Socket\SocketAddress;
use CT\AmpPool\WorkerPool;

final class SocketListenerProvider
{
    /** @var array<string, SocketListener> */
    private array $socketListeners  = [];
    
    public function __construct(private readonly WorkerPool $workerPool) {}
    
    public function listen(int $workerId, SocketAddress|string $address): void
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
        
        $this->socketListeners[$stringAddress] = new SocketListener($address, $this->workerPool);
        $this->socketListeners[$stringAddress]->addWorker($workerId);
        $this->socketListeners[$stringAddress]->receiveLoop();
    }
    
    public function close(): void
    {
        $this->socketListeners = [];
    }
}