<?php
declare(strict_types=1);

namespace CT\AmpPool;

use PHPUnit\Framework\TestCase;

class WorkerPoolTest                extends TestCase
{
    public function testStart(): void
    {
        $workerPool                 = new WorkerPool;
        $workerPool->describeGroup();
        
    }
}
