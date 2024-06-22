<?php
declare(strict_types=1);

namespace CT\AmpPool\Worker\WorkerState;

use PHPUnit\Framework\TestCase;

class WorkerStateStorageTest        extends TestCase
{
    public function testEnqueueDequeue(): void
    {
        $workerStateStorage         = new WorkerStateStorage(1, 1, true);
        $workerStateStorageRead     = new WorkerStateStorage(1, 1);
        
        $workerStateStorage->jobEnqueued(100, false);
        $workerStateStorageRead->update();
        
        $this->assertEquals(1, $workerStateStorageRead->getJobCount());
        $this->assertEquals(100, $workerStateStorageRead->getJobWeight());
        $this->assertEquals(false, $workerStateStorageRead->isWorkerReady());
        
        $workerStateStorage->jobDequeued(100, true);
        
        $workerStateStorageRead->update();
        
        $this->assertEquals(0, $workerStateStorageRead->getJobCount());
        $this->assertEquals(0, $workerStateStorageRead->getJobWeight());
        $this->assertEquals(true, $workerStateStorageRead->isWorkerReady());
    }
}
