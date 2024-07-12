<?php
declare(strict_types=1);

namespace IfCastle\AmpPool;

/**
 * Worker type enum.
 */
enum WorkerTypeEnum: string
{
    /**
     * Reactor worker type: a worker that reacts to events, handle external requests.
     */
    case REACTOR                    = 'reactor';
    /**
     * Job worker type: a worker that processes jobs.
     */
    case JOB                        = 'job';
    /**
     * Service worker type: a worker that provides background services.
     */
    case SERVICE                    = 'service';
}
