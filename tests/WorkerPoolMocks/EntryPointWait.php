<?php
declare(strict_types=1);

namespace CT\AmpPool\WorkerPoolMocks;

use Amp\TimeoutCancellation;
use CT\AmpPool\Worker\WorkerEntryPointInterface;
use CT\AmpPool\Worker\WorkerInterface;

final class EntryPointWait         implements WorkerEntryPointInterface
{
    private WorkerInterface $worker;
    
    public function initialize(WorkerInterface $worker): void
    {
        $this->worker               = $worker;
    }
    
    public function run(): void
    {
        $this->worker->awaitTermination(new TimeoutCancellation(5, 'Worker did not terminate in time.'));
    }
}