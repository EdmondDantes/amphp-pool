<?php
declare(strict_types=1);

namespace IfCastle\AmpPool\Coroutine;

use Amp\Future;
use Revolt\EventLoop\Suspension;

interface CoroutineInterface
{
    public function execute(): mixed;
    public function resolve(mixed $data = null): void;
    public function fail(\Throwable $exception): void;

    public function getSuspension(): ?Suspension;
    public function defineSuspension(Suspension $suspension): void;
    public function defineSchedulerSuspension(Suspension $schedulerSuspension): void;

    public function getFuture(): Future;
    public function getPriority(): int;
}
