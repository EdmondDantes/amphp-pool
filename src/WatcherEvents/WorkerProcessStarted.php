<?php
declare(strict_types=1);

namespace CT\AmpPool\WatcherEvents;

use Amp\Parallel\Context\Context;

final readonly class WorkerProcessStarted
{
    public function __construct(public int $workerId, public Context $context) {}
}