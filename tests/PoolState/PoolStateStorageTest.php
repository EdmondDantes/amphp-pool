<?php
declare(strict_types=1);

namespace CT\AmpPool\PoolState;

use PHPUnit\Framework\TestCase;

class PoolStateStorageTest          extends TestCase
{
    public function testSetGroupsGetGroups(): void
    {
        $poolStateStorage           = new PoolStateStorage(3);
        
        $groups                     = [
            1 => [1, 2],
            2 => [3, 3],
            3 => [4, 6],
        ];
        
        $poolStateStorage->setGroupsState($groups);
        
        $this->assertEquals($groups, $poolStateStorage->getGroupsState());
    }
    
    public function testReadGroup(): void
    {
        $poolStateStorage           = new PoolStateStorage(3);
        $poolStateStorageRead       = new PoolStateStorage;
        
        $groups                     = [
            1 => [1, 2],
            2 => [3, 3],
            3 => [4, 6],
        ];
        
        $poolStateStorage->setGroupsState($groups);
        $poolStateStorageRead->update();
        
        $this->assertEquals($groups, $poolStateStorageRead->getGroupsState());
        
    }
    
public function testSetWorkerGroupInfo(): void
    {
        $poolStateStorage           = new PoolStateStorage(3);
        $poolStateStorageRead       = new PoolStateStorage;
        
        $poolStateStorage->setWorkerGroupState(1, 1, 2);
        $poolStateStorage->setWorkerGroupState(2, 3, 3);
        $poolStateStorage->setWorkerGroupState(3, 4, 6);
        
        $this->assertEquals([
            1 => [1, 2],
            2 => [3, 3],
            3 => [4, 6],
        ], $poolStateStorage->getGroupsState());
        
        $poolStateStorageRead->update();
        
        $this->assertEquals([
            1 => [1, 2],
            2 => [3, 3],
            3 => [4, 6],
        ], $poolStateStorageRead->getGroupsState());
    }
}