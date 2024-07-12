<?php
declare(strict_types=1);

namespace IfCastle\AmpPool\Strategies\RestartStrategy;

final class RestartNever implements RestartStrategyInterface
{
    public function shouldRestart(mixed $exitResult): int
    {
        return RestartStrategyInterface::RESTART_NEVER;
    }

    public function getFailReason(): string
    {
        return 'Worker should never be restarted';
    }
}
