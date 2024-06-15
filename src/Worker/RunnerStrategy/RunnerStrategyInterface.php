<?php
declare(strict_types=1);

namespace CT\AmpPool\Worker\RunnerStrategy;

use Amp\Parallel\Context\Context;
use CT\AmpPool\WorkerGroupInterface;

interface RunnerStrategyInterface
{
    public function getScript(): string|array;
    
    /**
     * Send initial context from the `Watcher` process to a `Worker` process.
     * Returns a key to identify the connection between the `Watcher` and the `Worker`.
     *
     * @param Context              $processContext
     * @param int                  $workerId
     * @param WorkerGroupInterface $group
     *
     * @return string
     */
    public function sendPoolContext(Context $processContext, int $workerId, WorkerGroupInterface $group): string;
    
    public function shouldProvideSocketTransport(): bool;
}