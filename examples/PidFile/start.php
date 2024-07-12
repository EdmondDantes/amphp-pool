<?php
declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use Amp\ByteStream;
use Amp\Log\ConsoleFormatter;
use Amp\Log\StreamHandler;
use Examples\HttpServer\HttpReactor;
use IfCastle\AmpPool\WorkerGroup;
use IfCastle\AmpPool\WorkerPool;
use IfCastle\AmpPool\WorkerTypeEnum;
use Monolog\Logger;
use Monolog\Processor\PsrLogMessageProcessor;

$logHandler = new StreamHandler(ByteStream\getStdout());
$logHandler->pushProcessor(new PsrLogMessageProcessor());
$logHandler->setFormatter(new ConsoleFormatter());

$logger = new Logger('server');
$logger->pushHandler($logHandler);
$logger->useLoggingLoopDetection(false);

// 1. Create a worker pool with a logger
$workerPool = new WorkerPool(logger: $logger, pidFile: true);

// 2. Fill the worker pool with workers.
// We create a group of workers with the Reactor type, which are intended to handle incoming connections.
// The HttpReactor class is the entry point for the workers in this group.
// Please see the HttpReactor class for more details.
$workerPool->describeGroup(new WorkerGroup(
    entryPointClass: HttpReactor::class,
    workerType: WorkerTypeEnum::REACTOR,
    minWorkers: 1
));

// 3. Run the worker pool
// Start the main loop of the worker pool
// Now the server is ready to accept incoming connections.
// Try http://127.0.0.1:9095/ in your browser.
$workerPool->applyGlobalErrorHandler();
$workerPool->run();
