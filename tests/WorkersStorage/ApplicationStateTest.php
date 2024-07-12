<?php
declare(strict_types=1);

namespace IfCastle\AmpPool\WorkersStorage;

use PHPUnit\Framework\TestCase;

class ApplicationStateTest extends TestCase
{
    public function testWriteRead(): void
    {
        $workerStorage              = WorkersStorageMemory::instanciate(5);
        $applicationState           = ApplicationState::instanciate($workerStorage, 5, false);

        $this->fillApplicationState($applicationState);
        $applicationState->update();

        $applicationState2          = ApplicationState::instanciate($workerStorage, 5, false);
        $applicationState2->read();

        $this->assertEquals($applicationState->toArray(), $applicationState2->toArray());
    }

    private function fillApplicationState(ApplicationState $applicationState): void
    {
        $applicationState->setStartedAt(\rand(0, 1000));
        $applicationState->setLastRestartedAt(\rand(0, 1000));
        $applicationState->setRestartsCount(\rand(0, 1000));
        $applicationState->setWorkersErrors(\rand(0, 1000));
        $applicationState->setMemoryFree(\rand(0, 1000));
        $applicationState->setMemoryTotal(\rand(0, 1000));
        $applicationState->setLoadAverage(\rand(0, 1000) / 1000);
    }
}
