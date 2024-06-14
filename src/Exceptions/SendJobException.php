<?php
declare(strict_types=1);

namespace CT\AmpCluster\Exceptions;

final class SendJobException extends \RuntimeException
{
    public function __construct(int $workerGroupId, int $countTry)
    {
        parent::__construct("Failed send job to worker in group $workerGroupId after $countTry tries");
    }
}