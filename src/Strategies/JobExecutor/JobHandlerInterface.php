<?php
declare(strict_types=1);

namespace IfCastle\AmpPool\Strategies\JobExecutor;

use Amp\Cancellation;
use IfCastle\AmpPool\Coroutine\CoroutineInterface;

interface JobHandlerInterface
{
    public function handleJob(string $data, ?CoroutineInterface $coroutine = null, ?Cancellation $cancellation = null): mixed;
}
