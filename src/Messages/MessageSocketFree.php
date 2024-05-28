<?php
declare(strict_types=1);

namespace CT\AmpServer\Messages;

final readonly class MessageSocketFree
{
    public function __construct(public string|null $socketId = null) {}
}