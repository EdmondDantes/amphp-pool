<?php
declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use CT\AmpPool\WorkerPool;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Examples\HttpServer\HttpReactor;
use CT\AmpPool\WorkerGroup;
use CT\AmpPool\WorkerTypeEnum;

$logger = new Logger('server');
$logger->pushHandler(new StreamHandler('php://stdout'));
$logger->useLoggingLoopDetection(false);

// 1. Create a worker pool with a logger
$workerPool = new WorkerPool(logger: $logger);

// 2. Fill the worker pool with workers.
// We create a group of workers with the Reactor type, which are intended to handle incoming connections.
// The HttpReactor class is the entry point for the workers in this group.
// Please see the HttpReactor class for more details.
$workerPool->describeGroup(new WorkerGroup(
    entryPointClass: HttpReactor::class,
    workerType: WorkerTypeEnum::REACTOR,
    maxWorkers: 5
));

// 3. Run the worker pool
// Start the main loop of the worker pool
// Now the server is ready to accept incoming connections.
// Try http://127.0.0.1:9095/ in your browser.
$workerPool->run();