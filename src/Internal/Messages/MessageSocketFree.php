<?php
declare(strict_types=1);

namespace CT\AmpPool\Internal\Messages;

/**
 * @internal
 */
final readonly class MessageSocketFree
{
    public function __construct(public string|null $socketId = null) {}
}