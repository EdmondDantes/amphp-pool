<?php
declare(strict_types=1);

namespace CT\AmpServer\SocketPipe;

use Amp\Cancellation;
use Amp\DeferredFuture;
use Amp\ForbidCloning;
use Amp\ForbidSerialization;
use Amp\Serialization\SerializationException;
use Amp\Socket\BindContext;
use Amp\Socket\ResourceSocket;
use Amp\Socket\ServerSocket;
use Amp\Socket\Socket;
use Amp\Socket\SocketAddress;
use Amp\Socket\SocketException;
use Amp\Sync\Channel;
use Amp\Sync\ChannelException;
use CT\AmpServer\Messages\MessagePingPong;
use CT\AmpServer\Messages\MessageSocketTransfer;

final class ServerSocketFactoryWindows implements ServerSocket
{
    use ForbidCloning;
    use ForbidSerialization;
    
    private readonly DeferredFuture $onClose;
    
    public function __construct(private readonly Channel $channel, private readonly SocketAddress $socketAddress, private readonly BindContext $bindContext)
    {
        $this->onClose              = new DeferredFuture();
    }
    
    
    public function close(): void
    {
        if (!$this->onClose->isComplete()) {
            $this->onClose->complete();
        }
    }
    
    public function isClosed(): bool
    {
        return $this->onClose->isComplete();
    }
    
    public function onClose(\Closure $onClose): void
    {
        $this->onClose->getFuture()->finally($onClose);
    }
    
    /**
     * @throws SerializationException
     * @throws ChannelException
     * @throws SocketException
     */
    public function accept(?Cancellation $cancellation = null): ?Socket
    {
        $message                    = $this->channel->receive($cancellation);
        
        if($message instanceof MessagePingPong) {
            $this->channel->send(new MessagePingPong);
            
            while ($message = $this->channel->receive($cancellation)) {
                if($message instanceof MessageSocketTransfer) {
                    break;
                }
            }
        }
        
        if(false === $message instanceof MessageSocketTransfer) {
            throw new SocketException('Invalid message received. Required MessageSocketTransfer. Got: '.get_debug_type($message));
        }
        
        $socket                     = \socket_wsaprotocol_info_import($message->socketId);
        
        if(false === $socket) {
            throw new SocketException('Failed importing socket from ID: '.$message->socketId);
        }
        
        \socket_wsaprotocol_info_release($message->socketId);
        
        return ResourceSocket::fromServerSocket(\socket_export_stream($socket));
    }
    
    public function getAddress(): SocketAddress
    {
        return $this->socketAddress;
    }
    
    public function getBindContext(): BindContext
    {
        return $this->bindContext;
    }
}