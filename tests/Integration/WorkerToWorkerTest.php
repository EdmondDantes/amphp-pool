<?php
declare(strict_types=1);

namespace CT\AmpServer\Integration;

use CT\AmpServer\WorkerPool;
use CT\AmpServer\WorkerTypeEnum;
use PHPUnit\Framework\TestCase;

class WorkerToWorkerTest            extends TestCase
{
    public function testWorkerToWorkerMessage(): void
    {
        $workerPool                 = new WorkerPool();
        $workerPool->fillWorkersGroup(WorkerTestEntryPoint::class, WorkerTypeEnum::REACTOR, 1);
        $workerPool->fillWorkersGroup(WorkerTestEntryPoint::class, WorkerTypeEnum::JOB, 1);
        $workerPool->run();
        $workerPool->mainLoop();
        
        $tmpFile                    = sys_get_temp_dir() . WorkerTestEntryPoint::RESULT_FILE;
        $this->assertFileExists($tmpFile);
        $content                    = file_get_contents($tmpFile);
        $this->assertEquals('OK', $content, 'Worker to Worker message failed');
    }
}