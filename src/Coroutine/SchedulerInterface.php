<?php
declare(strict_types=1);

namespace IfCastle\AmpPool\Coroutine;

use Amp\Cancellation;
use Amp\Future;

interface SchedulerInterface
{
    public function run(CoroutineInterface $coroutine, ?Cancellation $cancellation = null): Future;
    public function awaitAll(?Cancellation $cancellation = null): void;
    public function stopAll(?\Throwable $exception = null): void;
    public function getCoroutinesCount(): int;
}
