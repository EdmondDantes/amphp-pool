<?php
declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use CT\AmpServer\WorkerPool;
use CT\AmpServer\WorkerEntryPoint;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

$logger = new Logger('cluster');
$logger->pushHandler(new StreamHandler('php://stdout'));
$logger->useLoggingLoopDetection(false);

$workerPool                         = new WorkerPool(reactorCount: 1, jobCount: 0, logger: $logger);
$workerPool->fillWorkersWith(WorkerEntryPoint::class);
$workerPool->run();
$workerPool->mainLoop();