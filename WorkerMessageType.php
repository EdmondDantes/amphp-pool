<?php
declare(strict_types=1);

namespace CT\AmpServer;

enum WorkerMessageType: string
{
    case PING                       = 'ping';
    case PONG                       = 'pong';
    case DATA                       = 'data';
    case LOG                        = 'log';
    /**
     * Worker is ready to receive jobs.
     */
    case READY                      = 'ready';
    /**
     *
     */
    case JOB                        = 'job';
    case DONE                       = 'done';
}
