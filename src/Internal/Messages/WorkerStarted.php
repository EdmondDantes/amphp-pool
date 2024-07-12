<?php
declare(strict_types=1);

namespace IfCastle\AmpPool\Internal\Messages;

final readonly class WorkerStarted
{
    public function __construct(public int $workerId, public bool $isOk = true)
    {
    }
}
