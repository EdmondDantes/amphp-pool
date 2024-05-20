<?php
declare(strict_types=1);

namespace CT\AmpServer\SocketPipe;

use Amp\Serialization\SerializationException;
use Amp\Socket\BindContext;
use Amp\Socket\ServerSocket;
use Amp\Socket\ServerSocketFactory;
use Amp\Socket\SocketAddress;
use Amp\Socket\SocketException;
use Amp\Sync\Channel;
use Amp\Sync\ChannelException;
use CT\AmpServer\Messages\MessageSocketListen;

final class SocketPipeFactoryWindows implements ServerSocketFactory
{
    public function __construct(private readonly Channel $channel) {}
    
    
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
        
        return new ServerSocketFactoryWindows($this->channel, $address, $bindContext);
    }
}