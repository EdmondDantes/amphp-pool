<?php
declare(strict_types=1);

namespace IfCastle\AmpPool\WorkersStorage;

use PHPUnit\Framework\TestCase;

class WorkerStateTest extends TestCase
{
    public function testWriteRead(): void
    {
        $workerStorage              = WorkersStorageMemory::instanciate(5);
        $workerState                = $workerStorage->getWorkerState(1);

        $this->fillWorkerState($workerState);
        $workerState->update();

        $workerState2               = $workerStorage->getWorkerState(1);
        $workerState2->read();

        $this->assertEquals($workerState, $workerState2);
    }

    public function testUpdateStateSegment(): void
    {
        $workerStorage              = WorkersStorageMemory::instanciate(5);
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
        $workerStorage              = WorkersStorageMemory::instanciate(5);
        $workerState                = $workerStorage->getWorkerState(1);

        $this->fillWorkerState($workerState);
        $workerState->update();

        $workerState2               = $workerStorage->getWorkerState(1);
        $workerState2->read();
        $this->assertEquals($workerState, $workerState2);

        $workerState->startedAt     = \time();
        $workerState->finishedAt    = \time();
        $workerState->updatedAt     = \time();
        $workerState->updateTimeSegment();
        $workerState2->read();

        $this->assertEquals($workerState, $workerState2);
    }

    public function testUpdateMemorySegment(): void
    {
        $workerStorage              = WorkersStorageMemory::instanciate(5);
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
        $workerStorage              = WorkersStorageMemory::instanciate(5);
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
        $workerStorage              = WorkersStorageMemory::instanciate(5);
        $workerState                = $workerStorage->getWorkerState(2);

        $this->fillWorkerState($workerState);
        $workerState->update();

        $workerState2               = $workerStorage->getWorkerState(2);
        $workerState2->read();
        $this->assertEquals($workerState, $workerState2);

        $workerState->jobAccepted   += 100;
        $workerState->jobProcessed  += 50;
        $workerState->jobProcessing += 5;
        $workerState->jobErrors     += 10;
        $workerState->jobRejected   += 5;

        $workerState->jobEnqueued(15, true);

        $workerState->updateJobSegment();
        $workerState2->read();
        $this->assertEquals($workerState, $workerState2);

        $workerState->jobDequeued(15, false);
        $workerState2->read();

        $this->assertEquals($workerState, $workerState2);
    }

    public function testUpdateShouldBeStarted(): void
    {
        $workerStorage              = WorkersStorageMemory::instanciate(5);
        $workerState                = $workerStorage->getWorkerState(2);

        $this->fillWorkerState($workerState);
        $isShouldBeStarted          = $workerState->isShouldBeStarted();
        $workerState->update();

        $isShouldBeStarted          = !$isShouldBeStarted;

        $workerState->updateShouldBeStarted($isShouldBeStarted);

        $workerState2               = $workerStorage->getWorkerState(2);
        $workerState2->read();
        $this->assertEquals($workerState2->isShouldBeStarted(), $isShouldBeStarted);
    }

    public function testUpdateShutdownErrors(): void
    {
        $workerStorage              = WorkersStorageMemory::instanciate(5);
        $workerState                = $workerStorage->getWorkerState(2);

        $this->fillWorkerState($workerState);
        $workerState->update();

        $workerState2               = $workerStorage->getWorkerState(2);
        $workerState2->read();
        $this->assertEquals($workerState, $workerState2);

        $workerState->shutdownErrors++;
        $workerState->increaseAndUpdateShutdownErrors();
        $workerState2->read();

        $this->assertEquals($workerState->getShutdownErrors(), $workerState2->getShutdownErrors());
    }

    public function testUnpackItem(): void
    {
        $workerStorage              = WorkersStorageMemory::instanciate(5);
        $workerState                = $workerStorage->getWorkerState(1);

        $this->fillWorkerState($workerState);
        $workerState->update();

        $workerState2               = WorkerState::unpackItem($workerStorage->readWorkerState(1));

        $this->assertEquals($workerState->getWorkerId(), $workerState2->getWorkerId());
        $this->assertEquals($workerState->getGroupId(), $workerState2->getGroupId());
        $this->assertEquals($workerState->isShouldBeStarted(), $workerState2->isShouldBeStarted());
        $this->assertEquals($workerState->isReady(), $workerState2->isReady());
        $this->assertEquals($workerState->getPid(), $workerState2->getPid());
        $this->assertEquals($workerState->getTotalReloaded(), $workerState2->getTotalReloaded());
        $this->assertEquals($workerState->getShutdownErrors(), $workerState2->getShutdownErrors());
        $this->assertEquals($workerState->getWeight(), $workerState2->getWeight());
        $this->assertEquals($workerState->getStartedAt(), $workerState2->getStartedAt());
        $this->assertEquals($workerState->getFinishedAt(), $workerState2->getFinishedAt());
        $this->assertEquals($workerState->getUpdatedAt(), $workerState2->getUpdatedAt());
        $this->assertEquals($workerState->getPhpMemoryUsage(), $workerState2->getPhpMemoryUsage());
        $this->assertEquals($workerState->getPhpMemoryPeakUsage(), $workerState2->getPhpMemoryPeakUsage());
        $this->assertEquals($workerState->getConnectionsAccepted(), $workerState2->getConnectionsAccepted());
        $this->assertEquals($workerState->getConnectionsProcessed(), $workerState2->getConnectionsProcessed());
        $this->assertEquals($workerState->getConnectionsErrors(), $workerState2->getConnectionsErrors());
        $this->assertEquals($workerState->getConnectionsRejected(), $workerState2->getConnectionsRejected());
        $this->assertEquals($workerState->getConnectionsProcessing(), $workerState2->getConnectionsProcessing());
        $this->assertEquals($workerState->getJobAccepted(), $workerState2->getJobAccepted());
        $this->assertEquals($workerState->getJobProcessed(), $workerState2->getJobProcessed());
        $this->assertEquals($workerState->getJobProcessing(), $workerState2->getJobProcessing());
        $this->assertEquals($workerState->getJobErrors(), $workerState2->getJobErrors());
        $this->assertEquals($workerState->getJobRejected(), $workerState2->getJobRejected());
    }

    private function fillWorkerState(WorkerState $workerState): void
    {
        $workerState->groupId        = 2;
        $workerState->pid            = 8994445;
        $workerState->shouldBeStarted= true;
        $workerState->isReady        = true;
        $workerState->totalReloaded  = 45;
        $workerState->weight         = 1000;
        $workerState->firstStartedAt = \time();
        $workerState->startedAt      = \time();
        $workerState->finishedAt     = \time();
        $workerState->updatedAt      = \time();
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
