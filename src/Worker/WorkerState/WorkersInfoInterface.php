<?php
declare(strict_types=1);

namespace CT\AmpPool\Worker\WorkerState;

interface WorkersInfoInterface
{
    public function getWorkerState(int $workerId): ?WorkerState;
}
