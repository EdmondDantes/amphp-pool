<?php
declare(strict_types=1);

namespace CT\AmpPool\Integration;

use CT\AmpPool\WorkerPool;
use CT\AmpPool\WorkerTypeEnum;
use PHPUnit\Framework\TestCase;

class WorkerToWorkerTest            extends TestCase
{
    public function testWorkerToWorkerMessage(): void
    {
        return;
        
        $workerPool                 = new WorkerPool();
        $workerPool->fillWorkersGroup(WorkerTestEntryPoint::class, WorkerTypeEnum::REACTOR, 1);
        //$workerPool->fillWorkersGroup(WorkerTestEntryPoint::class, WorkerTypeEnum::JOB, 1);
        $workerPool->run();
        $workerPool->mainLoop();
        
        $tmpFile                    = sys_get_temp_dir() . WorkerTestEntryPoint::RESULT_FILE;
        $this->assertFileExists($tmpFile);
        $content                    = file_get_contents($tmpFile);
        $this->assertEquals('OK', $content, 'Worker to Worker message failed');
    }
}