<?php
declare(strict_types=1);

namespace CT\AmpPool\PoolState;

interface PoolStateInterface        extends PoolStateReadableInterface
{
    public function setGroupsState(array $groups): void;
    
    public function setWorkerGroupState(int $groupId, int $lowestWorkerId, int $highestWorkerId): void;
}