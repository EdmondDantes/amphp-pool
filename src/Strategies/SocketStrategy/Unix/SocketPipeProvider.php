<?php
declare(strict_types=1);

namespace CT\AmpPool\Strategies\SocketStrategy\Unix;

use Amp\ByteStream\ResourceStream;
use Amp\Cancellation;
use Amp\Cluster\ServerSocketPipeProvider;
use Amp\Parallel\Ipc\IpcHub;
use Amp\Socket\ResourceSocket;
use Amp\Socket\Socket;
use Amp\TimeoutCancellation;
use const Amp\Process\IS_WINDOWS;

final class SocketPipeProvider
{
    private ServerSocketPipeProvider $provider;
    
    public function __construct(private readonly IpcHub $hub, private readonly int $timeout = 5)
    {
        if (IS_WINDOWS) {
            throw new \Error(__CLASS__.' can\'t be used under Windows OS');
        }
        
        $this->provider             = new ServerSocketPipeProvider;
    }
    
    public function createSocketTransport(string $ipcKey): SocketPipeTransport
    {
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
        $this->provider->provideFor($pipeTransport->socket, $cancellation);
    }
}