<?php
declare(strict_types=1);

namespace IfCastle\AmpPool\Integration\WorkerIpc;

use IfCastle\AmpPool\Strategies\RestartStrategy\RestartNever;
use IfCastle\AmpPool\WorkerGroup;
use IfCastle\AmpPool\WorkerPool;
use IfCastle\AmpPool\WorkerTypeEnum;
use PHPUnit\Framework\TestCase;

class WorkerToWorkerTest extends TestCase
{
    public function testWorkerToWorkerMessage(): void
    {
        $workerPool                 = new WorkerPool();

        $workerPool->describeGroup(new WorkerGroup(
            entryPointClass: EntryPoint::class,
            workerType     : WorkerTypeEnum::JOB,
            minWorkers     : 1,
            groupName      : EntryPoint::GROUP1,
            restartStrategy: new RestartNever
        ));

        $workerPool->describeGroup(new WorkerGroup(
            entryPointClass: EntryPoint::class,
            workerType     : WorkerTypeEnum::REACTOR,
            minWorkers     : 1,
            groupName      : EntryPoint::GROUP2,
            jobGroups      : [1],
            restartStrategy: new RestartNever
        ));

        $workerPool->run();

        $this->assertFileExists(EntryPoint::getFile());

        // Assert file content
        $this->assertEquals(EntryPoint::GROUP2.EntryPoint::WAS_HANDLED, \file_get_contents(EntryPoint::getFile()));
    }

    protected function setUp(): void
    {
        EntryPoint::removeFile();
    }

    protected function tearDown(): void
    {
        EntryPoint::removeFile();
    }
}
