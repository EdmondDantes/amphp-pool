<?php
declare(strict_types=1);

namespace CT\AmpServer;

enum WorkerTypeEnum: string
{
    case REACTOR                    = 'reactor';
    case JOB                        = 'job';
    case SERVICE                    = 'service';
}
