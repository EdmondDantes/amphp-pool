<?php
declare(strict_types=1);

namespace IfCastle\AmpPool\WatcherEvents;

use Amp\Parallel\Context\Context;
use IfCastle\AmpPool\WorkerGroupInterface;

final readonly class WorkerProcessTerminating
{
    public function __construct(public int $workerId, public WorkerGroupInterface $workerGroup, public Context $context, mixed $exitResult = null)
    {
    }
}
