<?php
declare(strict_types=1);

namespace CT\AmpPool\JobIpc;

use CT\AmpPool\Exceptions\RemoteException;

final readonly class JobResponse
{
    public function __construct(public int    $jobId,
                                public int    $fromWorkerId,
                                public int    $workerGroupId,
                                public int    $errorCode,
                                public string $data,
                                public ?RemoteException $exception = null
    ) {}
}