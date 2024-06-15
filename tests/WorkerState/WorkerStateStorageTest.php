<?php
declare(strict_types=1);

namespace CT\AmpCluster\WorkerState;

use CT\AmpCluster\Worker\WorkerState\WorkerStateStorage;
use PHPUnit\Framework\TestCase;

class WorkerStateStorageTest        extends TestCase
{
    public function testWriteRead(): void
    {
        $workerStateStorage         = new WorkerStateStorage(1, 1, true);
        $workerStateStorageRead     = new WorkerStateStorage(1);
        
        $workerStateStorage->workerReady();
        $workerStateStorageRead->update();
        
        $this->assertTrue($workerStateStorageRead->isWorkerReady(), 'Worker is ready');
    }
    
    public function testWriteReadJobCount(): void
    {
        $workerStateStorage         = new WorkerStateStorage(1, 1, true);
        $workerStateStorageRead     = new WorkerStateStorage(1);
        
        $workerStateStorage->workerReady();
        $workerStateStorage->incrementJobCount();
        $workerStateStorage->incrementJobCount();
        $workerStateStorage->incrementJobCount();
        $workerStateStorageRead->update();
        
        $this->assertEquals(3, $workerStateStorageRead->getJobCount(), 'Job count');
    }
    
    public function testWriteReadJobCountDecrement(): void
    {
        $workerStateStorage         = new WorkerStateStorage(1, 1, true);
        $workerStateStorageRead     = new WorkerStateStorage(1);
        
        $workerStateStorage->workerReady();
        $workerStateStorage->incrementJobCount();
        $workerStateStorage->incrementJobCount();
        $workerStateStorage->incrementJobCount();
        $workerStateStorage->decrementJobCount();
        $workerStateStorageRead->update();
        
        $this->assertEquals(2, $workerStateStorageRead->getJobCount(), 'Job count');
    }
}
