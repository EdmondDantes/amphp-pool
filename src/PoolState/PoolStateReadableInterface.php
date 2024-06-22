<?php
declare(strict_types=1);

namespace CT\AmpPool\PoolState;

interface PoolStateReadableInterface
{
    public function getGroupsState(): array;
    
    public function findGroupState(int $groupId): array;
    
    public function update(): void;
}