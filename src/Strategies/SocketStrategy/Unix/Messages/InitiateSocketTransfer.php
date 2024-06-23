<?php
declare(strict_types=1);

namespace CT\AmpPool\Strategies\SocketStrategy\Unix\Messages;

final readonly class InitiateSocketTransfer
{
    public function __construct(public int $workerId, public int $groupId) {}
}