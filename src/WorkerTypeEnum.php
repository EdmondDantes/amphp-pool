<?php
declare(strict_types=1);

namespace CT\AmpCluster;

enum WorkerTypeEnum: string
{
    case REACTOR                    = 'reactor';
    case JOB                        = 'job';
    case SERVICE                    = 'service';
}
