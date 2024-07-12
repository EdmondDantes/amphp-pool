<?php
declare(strict_types=1);

namespace IfCastle\AmpPool\Worker\Internal;

use Revolt\EventLoop;

/**
 * @internal
 */
final class PeriodicTask
{
    private string $id;

    public function __construct(float $delay, \Closure $task)
    {
        $this->id = EventLoop::repeat($delay, $task);
    }

    public function cancel(): void
    {
        EventLoop::cancel($this->id);
    }

    public function __destruct()
    {
        $this->cancel();
    }
}
