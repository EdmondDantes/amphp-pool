<?php
declare(strict_types=1);

namespace CT\AmpServer\Exceptions;

final class NoAvailableWorkers extends \RuntimeException
{
    public function __construct(int $workerGroupId)
    {
        parent::__construct("No available workers in group $workerGroupId");
    }
}