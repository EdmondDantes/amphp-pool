<?php
declare(strict_types=1);

namespace IfCastle\AmpPool\Strategies\SocketStrategy\Unix\Messages;

final readonly class SocketTransferInfo
{
    public function __construct(public string $key, public string $uri)
    {
    }
}
