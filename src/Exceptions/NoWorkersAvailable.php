<?php
declare(strict_types=1);

namespace IfCastle\AmpPool\Exceptions;

final class NoWorkersAvailable extends \RuntimeException
{
    public function __construct(array $groups)
    {
        parent::__construct('No available workers in groups: ' . \implode(', ', $groups) . '.');
    }
}
