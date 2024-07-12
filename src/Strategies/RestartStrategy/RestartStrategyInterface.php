<?php
declare(strict_types=1);

namespace IfCastle\AmpPool\Strategies\RestartStrategy;

/**
 * Worker Restart Strategy, which defines the rules by which a worker can be restarted.
 */
interface RestartStrategyInterface
{
    public const int RESTART_IMMEDIATELY = 0;
    public const int RESTART_NEVER = -1;

    /**
     * Returns the seconds to wait before restarting the worker.
     * If the worker should not be restarted, it should return -1.
     *
     *
     */
    public function shouldRestart(mixed $exitResult): int;

    /**
     * Returns the reason why the worker should not be restarted from the last call to shouldRestart.
     *
     */
    public function getFailReason(): string;
}
