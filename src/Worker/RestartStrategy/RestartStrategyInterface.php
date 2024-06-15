<?php
declare(strict_types=1);

namespace CT\AmpPool\Worker\RestartStrategy;

/**
 * Worker Restart Strategy, which defines the rules by which a worker can be restarted.
 */
interface RestartStrategyInterface
{
    /**
     * Returns the seconds to wait before restarting the worker.
     * If the worker should not be restarted, it should return -1.
     *
     * @param   mixed   $exitResult
     *
     * @return  int
     */
    public function shouldRestart(mixed $exitResult): int;
    
    /**
     * Returns the reason why the worker should not be restarted from the last call to shouldRestart.
     *
     * @return  string
     */
    public function getFailReason(): string;
}