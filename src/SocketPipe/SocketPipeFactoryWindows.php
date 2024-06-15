<?php
declare(strict_types=1);

namespace CT\AmpPool\SocketPipe;

use Amp\Serialization\SerializationException;
use Amp\Socket\BindContext;
use Amp\Socket\ServerSocket;
use Amp\Socket\ServerSocketFactory;
use Amp\Socket\SocketAddress;
use Amp\Socket\SocketException;
use Amp\Sync\Channel;
use Amp\Sync\ChannelException;
use CT\AmpPool\Messages\MessageSocketListen;
use CT\AmpPool\Worker\Worker;

final class SocketPipeFactoryWindows implements ServerSocketFactory
{
    public function __construct(private readonly Channel $channel, private readonly Worker $worker) {}
    
    
    public function listen(SocketAddress|string $address, ?BindContext $bindContext = null): ServerSocket
    {
        $bindContext                ??= new BindContext();
        
        if (false === $address instanceof SocketAddress) {
            // Normalize to SocketAddress here to avoid throwing exception for invalid strings at a receiving end.
            $address                = SocketAddress\fromString($address);
        }
        
        try {
            $this->channel->send(new MessageSocketListen($address));
        } catch (ChannelException|SerializationException $exception) {
            throw new SocketException(
                          'Failed sending request to bind server socket: ' . $exception->getMessage(),
                previous: $exception,
            );
        }
        
        $factory                    = new ServerSocketFactoryWindows($this->channel, $address, $bindContext);
        
        /**
         * Subscribe to the worker events for catching the MessageSocketTransfer message.
         */
        $this->worker->addEventHandler($factory->workerEventHandler(...));
        
        return $factory;
    }
}