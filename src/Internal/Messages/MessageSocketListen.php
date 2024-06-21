<?php
declare(strict_types=1);

namespace CT\AmpPool\Internal\Messages;

use Amp\Socket\SocketAddress;

final readonly class MessageSocketListen
{
    public function __construct(public SocketAddress $address) {}
}