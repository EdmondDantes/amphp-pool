<?php
declare(strict_types=1);

namespace WorkersStorage;

use CT\AmpPool\WorkersStorage\WorkersStorageMemory;
use CT\AmpPool\WorkersStorage\WorkerState;
use PHPUnit\Framework\TestCase;

class WorkerStateTest extends TestCase
{
    public function testWriteRead(): void
    {
        $workerStorage              = new WorkersStorageMemory(WorkerState::class, 5);
        $workerState                = $workerStorage->getWorkerState(1);
        
        $this->fillWorkerState($workerState);
        $workerState->update();
        
        $workerState2               = $workerStorage->getWorkerState(1);
        $workerState2->read();
        
        $this->assertEquals($workerState, $workerState2);
    }
    
    public function testUpdateStateSegment(): void
    {
        $workerStorage              = new WorkersStorageMemory(WorkerState::class, 5);
        $workerState                = $workerStorage->getWorkerState(1);
        
        $this->fillWorkerState($workerState);
        $workerState->update();
        
        $workerState2               = $workerStorage->getWorkerState(1);
        $workerState2->read();
        $this->assertEquals($workerState, $workerState2);
        
        $workerState->isReady       = false;
        $workerState->totalReloaded++;
        $workerState->weight        += 100;
        $workerState->updateStateSegment();
        $workerState2->read();
        
        $this->assertEquals($workerState, $workerState2);
    }
    
    public function testUpdateTimeSegment(): void
    {
        $workerStorage              = new WorkersStorageMemory(WorkerState::class, 5);
        $workerState                = $workerStorage->getWorkerState(1);
        
        $this->fillWorkerState($workerState);
        $workerState->update();
        
        $workerState2               = $workerStorage->getWorkerState(1);
        $workerState2->read();
        $this->assertEquals($workerState, $workerState2);
        
        $workerState->startedAt     = time();
        $workerState->finishedAt    = time();
        $workerState->updatedAt     = time();
        $workerState->updateTimeSegment();
        $workerState2->read();
        
        $this->assertEquals($workerState, $workerState2);
    }
    
    public function testUpdateMemorySegment(): void
    {
        $workerStorage              = new WorkersStorageMemory(WorkerState::class, 5);
        $workerState                = $workerStorage->getWorkerState(1);
        
        $this->fillWorkerState($workerState);
        $workerState->update();
        
        $workerState2               = $workerStorage->getWorkerState(1);
        $workerState2->read();
        $this->assertEquals($workerState, $workerState2);
        
        $workerState->phpMemoryUsage += 100000;
        $workerState->phpMemoryPeakUsage += 100000;
        $workerState->updateMemorySegment();
        $workerState2->read();
        
        $this->assertEquals($workerState, $workerState2);
    }
    
    public function testUpdateConnectionsSegment(): void
    {
        $workerStorage              = new WorkersStorageMemory(WorkerState::class, 5);
        $workerState                = $workerStorage->getWorkerState(3);
        
        $this->fillWorkerState($workerState);
        $workerState->update();
        
        $workerState2               = $workerStorage->getWorkerState(3);
        $workerState2->read();
        $this->assertEquals($workerState, $workerState2);
        
        $workerState->connectionsAccepted += 100;
        $workerState->connectionsProcessed += 50;
        $workerState->connectionsErrors += 10;
        $workerState->connectionsRejected += 5;
        $workerState->connectionsProcessing += 5;
        $workerState->updateConnectionsSegment();
        $workerState2->read();
        
        $this->assertEquals($workerState, $workerState2);
    }
    
    public function testUpdateJobSegment(): void
    {
        $workerStorage              = new WorkersStorageMemory(WorkerState::class, 5);
        $workerState                = $workerStorage->getWorkerState(2);
        
        $this->fillWorkerState($workerState);
        $workerState->update();
        
        $workerState2               = $workerStorage->getWorkerState(2);
        $workerState2->read();
        $this->assertEquals($workerState, $workerState2);
        
        $workerState->jobAccepted += 100;
        $workerState->jobProcessed += 50;
        $workerState->jobProcessing += 5;
        $workerState->jobErrors += 10;
        $workerState->jobRejected += 5;
        $workerState->updateJobSegment();
        $workerState2->read();
        
        $this->assertEquals($workerState, $workerState2);
    }
    
    private function fillWorkerState(WorkerState $workerState): void
    {
        $workerState->groupId        = 2;
        $workerState->shouldBeStarted= true;
        $workerState->isReady        = true;
        $workerState->totalReloaded  = 45;
        $workerState->weight         = 1000;
        $workerState->firstStartedAt = time();
        $workerState->startedAt      = time();
        $workerState->finishedAt     = time();
        $workerState->updatedAt      = time();
        $workerState->phpMemoryUsage = 1000000;
        $workerState->phpMemoryPeakUsage = 2000000;
        $workerState->connectionsAccepted = 100;
        $workerState->connectionsProcessed = 50;
        $workerState->connectionsErrors = 10;
        $workerState->connectionsRejected = 5;
        $workerState->connectionsProcessing = 5;
        $workerState->jobAccepted = 100;
        $workerState->jobProcessed = 50;
        $workerState->jobProcessing = 5;
        $workerState->jobErrors = 10;
        $workerState->jobRejected = 5;
    }
}
