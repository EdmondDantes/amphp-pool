<?php
declare(strict_types=1);

namespace CT\AmpCluster\SocketPipe;

use Amp\Socket\ResourceSocket;

final readonly class SocketPipeTransport
{
    public function __construct(public ?ResourceSocket $socket = null) {}
    
    public function close(): void
    {
        $this->socket?->close();
    }
    
    public function __destruct()
    {
        $this->socket?->close();
    }
}