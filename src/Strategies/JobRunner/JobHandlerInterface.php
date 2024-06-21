<?php
declare(strict_types=1);

namespace CT\AmpPool\Strategies\JobRunner;

use CT\AmpPool\Coroutine\CoroutineInterface;

interface JobHandlerInterface
{
    public function handleJob(string $data, CoroutineInterface $coroutine = null): mixed;
}