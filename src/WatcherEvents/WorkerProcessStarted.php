<?php
declare(strict_types=1);

namespace CT\AmpPool\WatcherEvents;

use Amp\Parallel\Context\Context;
use CT\AmpPool\WorkerGroupInterface;

final readonly class WorkerProcessStarted
{
    public function __construct(public int $workerId, public WorkerGroupInterface $workerGroup, public Context $context) {}
}