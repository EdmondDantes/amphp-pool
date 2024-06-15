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
        
        $poolStateStorage->setGroups($groups);
        
        $this->assertEquals($groups, $poolStateStorage->getGroups());
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
        
        $poolStateStorage->setGroups($groups);
        $poolStateStorageRead->update();
        
        $this->assertEquals($groups, $poolStateStorageRead->getGroups());
        
    }
    
public function testSetWorkerGroupInfo(): void
    {
        $poolStateStorage           = new PoolStateStorage(3);
        $poolStateStorageRead       = new PoolStateStorage;
        
        $poolStateStorage->setWorkerGroupInfo(1, 1, 2);
        $poolStateStorage->setWorkerGroupInfo(2, 3, 3);
        $poolStateStorage->setWorkerGroupInfo(3, 4, 6);
        
        $this->assertEquals([
            1 => [1, 2],
            2 => [3, 3],
            3 => [4, 6],
        ], $poolStateStorage->getGroups());
        
        $poolStateStorageRead->update();
        
        $this->assertEquals([
            1 => [1, 2],
            2 => [3, 3],
            3 => [4, 6],
        ], $poolStateStorageRead->getGroups());
    }
}