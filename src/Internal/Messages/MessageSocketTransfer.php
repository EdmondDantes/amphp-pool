<?php
declare(strict_types=1);

namespace CT\AmpPool\Internal\Messages;

/**
 * @internal
 */
final readonly class MessageSocketTransfer
{
    public function __construct(public string $socketId) {}
}