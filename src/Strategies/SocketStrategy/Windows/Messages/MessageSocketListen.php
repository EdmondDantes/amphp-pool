<?php
declare(strict_types=1);

namespace IfCastle\AmpPool\Strategies\SocketStrategy\Windows\Messages;

use Amp\Socket\SocketAddress;

/**
 * @internal
 */
final readonly class MessageSocketListen
{
    public function __construct(public SocketAddress $address)
    {
    }
}
