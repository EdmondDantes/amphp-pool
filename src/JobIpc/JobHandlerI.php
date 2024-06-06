<?php
declare(strict_types=1);

namespace CT\AmpServer\JobIpc;

use Amp\Cancellation;
use CT\AmpServer\WorkerI;

interface JobHandlerI
{
    /**
     * @param   mixed $data
     * @param   WorkerI $worker
     * @param   Cancellation $cancellation
     * @return  mixed
     */
    public function invokeJob(mixed $data, WorkerI $worker, Cancellation $cancellation): mixed;
}