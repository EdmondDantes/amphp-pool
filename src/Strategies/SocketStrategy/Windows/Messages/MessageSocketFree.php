<?php
declare(strict_types=1);

namespace CT\AmpPool\Strategies\SocketStrategy\Windows\Messages;

/**
 * @internal
 */
final readonly class MessageSocketFree
{
    public function __construct(public string|null $socketId = null) {}
}