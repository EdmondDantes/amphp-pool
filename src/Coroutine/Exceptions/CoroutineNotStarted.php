<?php
declare(strict_types=1);

namespace IfCastle\AmpPool\Coroutine\Exceptions;

class CoroutineNotStarted extends CoroutineTerminationException
{
    public function __construct()
    {
        parent::__construct('Coroutine not started');
    }
}
