<?php
declare(strict_types=1);

namespace CT\AmpPool\WorkersStorage;

use PHPUnit\Framework\TestCase;

class WorkersStorageTest extends TestCase
{
    public function testWriteRead(): void
    {
        $workerStorage              = WorkersStorageMemory::instanciate(10);
        $workerState                = $workerStorage->getWorkerState(2);
        $this->fillWorkerState($workerState);
        $workerState->update();

        $workerState2               = $workerStorage->getWorkerState(2);
        $workerState2->read();

        $this->assertEquals($workerState, $workerState2);
    }

    public function testOnlyRead(): void
    {
        $workerStorage              = WorkersStorageMemory::instanciate(10);
        $workerStorageReadOnly      = WorkersStorageMemory::instanciate();

        $workerState                = $workerStorage->getWorkerState(2);
        $this->fillWorkerState($workerState);
        $workerState->update();

        $workerState2               = $workerStorageReadOnly->getWorkerState(2);
        $workerState2->read();

        $this->assertEquals($workerState, $workerState2);
    }

    public function testReview(): void
    {
        $workerStorage              = WorkersStorageMemory::instanciate(10);
        $workerStorageReadOnly      = WorkersStorageMemory::instanciate();

        $workerState                = $workerStorage->getWorkerState(2);
        $this->fillWorkerState($workerState);
        $workerState->update();

        $workerState                = $workerStorage->reviewWorkerState(2);

        $workerState2               = $workerStorageReadOnly->reviewWorkerState(2);
        $this->assertEquals($workerState, $workerState2);
    }

    public function testForeachWorkers(): void
    {
        $workerStorage              = WorkersStorageMemory::instanciate(10);
        $workerStorageReadOnly      = WorkersStorageMemory::instanciate();

        $workerStates               = [];

        for($i = 1; $i <= 10; $i++) {
            $workerState            = $workerStorage->getWorkerState($i);
            $this->fillWorkerState($workerState);
            $workerState->update();

            $workerStates[$i-1]     = $workerStorage->reviewWorkerState($i);
        }

        $workerStatesReadOnly       = [];

        foreach($workerStorageReadOnly->foreachWorkers() as $workerState) {
            $workerStatesReadOnly[] = $workerState;
        }

        $this->assertEquals($workerStates, $workerStatesReadOnly);
    }

    public function testWriteException(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('This instance WorkersStorage is read-only');

        $workerStorageReadOnly      = WorkersStorageMemory::instanciate();

        $workerState2               = $workerStorageReadOnly->getWorkerState(2);
        $workerState2->update();
    }

    public function testWrongWorkerId(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid worker id provided');

        $workerStorage              = WorkersStorageMemory::instanciate(10);
        $workerStorage->getWorkerState(0);
    }

    public function testWorkerIdOutOfRange(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Worker id is out of range');

        $workerStorage              = WorkersStorageMemory::instanciate(10);
        $workerStorage->getWorkerState(11);
    }

    private function fillWorkerState(WorkerState $workerState): void
    {
        $workerState->groupId        = \rand(1, 1000000);
        $workerState->shouldBeStarted= \rand(0, 1) === 1;
        $workerState->isReady        = \rand(0, 1) === 1;
        $workerState->totalReloaded  = \rand(1, 1000000);
        $workerState->weight         = \rand(1, 1000000);
        $workerState->firstStartedAt = \rand(1, 1000000);
        $workerState->startedAt      = \rand(1, 1000000);
        $workerState->finishedAt     = \rand(1, 1000000);
        $workerState->updatedAt      = \rand(1, 1000000);
        $workerState->phpMemoryUsage = \rand(1, 1000000);
        $workerState->phpMemoryPeakUsage = \rand(1, 1000000);
        $workerState->connectionsAccepted = \rand(1, 1000000);
        $workerState->connectionsProcessed = \rand(1, 1000000);
        $workerState->connectionsErrors = \rand(1, 1000000);
        $workerState->connectionsRejected = \rand(1, 1000000);
        $workerState->connectionsProcessing = \rand(1, 1000000);
        $workerState->jobAccepted = \rand(1, 1000000);
        $workerState->jobProcessed = \rand(1, 1000000);
        $workerState->jobProcessing = \rand(1, 1000000);
        $workerState->jobErrors = \rand(1, 1000000);
        $workerState->jobRejected = \rand(1, 1000000);
    }
}
