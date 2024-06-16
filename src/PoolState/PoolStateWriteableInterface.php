<?php
declare(strict_types=1);

namespace CT\AmpPool\PoolState;

interface PoolStateWriteableInterface extends PoolStateReadableInterface
{
    public function setGroupsState(array $groups): void;
    
    public function setWorkerGroupState(int $groupId, int $lowestWorkerId, int $highestWorkerId): void;
}