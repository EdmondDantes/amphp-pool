<?php
declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use CT\AmpServer\WorkerPool;
use CT\AmpServer\WorkerEntryPoint;

$workerPool                         = new WorkerPool(1, 0);
$workerPool->fillWorkersWith(WorkerEntryPoint::class);
$workerPool->run();
$workerPool->mainLoop();