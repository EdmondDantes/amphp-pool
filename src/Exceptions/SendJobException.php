<?php
declare(strict_types=1);

namespace IfCastle\AmpPool\Exceptions;

final class SendJobException extends \RuntimeException
{
    public function __construct(array $groups, int $countTry)
    {
        parent::__construct("Failed send job to worker in group ".\implode(', ', $groups)." after $countTry tries");
    }
}
