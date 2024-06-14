<?php
declare(strict_types=1);

namespace CT\AmpCluster\JobIpc;

use Amp\Cancellation;
use CT\AmpCluster\Worker\WorkerInterface;

interface JobHandlerInterface
{
    /**
     * @param   mixed           $data
     * @param   WorkerInterface $worker
     * @param   Cancellation    $cancellation
     *
     * @return  mixed
     */
    public function invokeJob(mixed $data, WorkerInterface $worker, Cancellation $cancellation): mixed;
}