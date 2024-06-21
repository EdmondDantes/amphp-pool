<?php
declare(strict_types=1);

namespace CT\AmpPool\Internal\SocketPipe;

use Amp\ByteStream\ResourceStream;
use Amp\Cancellation;
use Amp\Cluster\ServerSocketPipeProvider;
use Amp\Parallel\Ipc\IpcHub;
use Amp\Socket\ResourceSocket;
use Amp\Socket\Socket;
use Amp\TimeoutCancellation;

final class SocketPipeProvider
{
    private ?ServerSocketPipeProvider $provider = null;
    private ?Socket $socket         = null;
    
    public function __construct(private readonly IpcHub $hub, private readonly int $timeout = 5)
    {
        // If windows, we can't use the ServerSocketPipeProvider
        if (PHP_OS_FAMILY !== 'Windows') {
            $this->provider         = new ServerSocketPipeProvider();
        }
    }
    
    public function used(): bool
    {
        return $this->provider !== null;
    }
    
    public function createSocketTransport(string $ipcKey): SocketPipeTransport|null
    {
        if($this->provider === null) {
            return null;
        }
        
        $socket                     = $this->hub->accept($ipcKey, new TimeoutCancellation($this->timeout));
        
        if (false === $socket instanceof ResourceSocket) {
            throw new \TypeError(\sprintf(
                                     'The %s instance returned from %s::accept() must also implement %s',
                                     Socket::class,
                                     \get_class($this->hub),
                                     ResourceStream::class,
                                 ));
        }
        
        return new SocketPipeTransport($socket);
    }
    
    public function provideFor(SocketPipeTransport $pipeTransport = null, Cancellation $cancellation = null): void
    {
        if($this->provider === null) {
            return;
        }
        
        $this->provider->provideFor($pipeTransport->socket, $cancellation);
    }
}