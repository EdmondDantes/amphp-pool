<?php
declare(strict_types=1);

namespace CT\AmpCluster\Worker\RestartPolicy;

final class RestartAlways implements RestartPolicyInterface
{
    public function shouldRestart(mixed $exitResult): int
    {
        // TODO: Implement shouldRestart() method.
    }
    
    public function getFailReason(): string
    {
        // TODO: Implement getFailReason() method.
    }
}