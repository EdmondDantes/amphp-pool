<?php
declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use CT\AmpPool\Telemetry\Prometheus\PrometheusGroup;
use CT\AmpPool\WorkerGroup;
use CT\AmpPool\WorkerPool;
use CT\AmpPool\WorkerTypeEnum;
use Examples\Prometheus\HttpReactorWithTelemetry;
use Monolog\Logger;
use Amp\ByteStream;
use Amp\Log\StreamHandler;
use Monolog\Processor\PsrLogMessageProcessor;
use Amp\Log\ConsoleFormatter;

$logHandler = new StreamHandler(ByteStream\getStdout());
$logHandler->pushProcessor(new PsrLogMessageProcessor());
$logHandler->setFormatter(new ConsoleFormatter());

$logger = new Logger('server');
$logger->pushHandler($logHandler);
$logger->useLoggingLoopDetection(false);

// 1. Create a worker pool with a logger
$workerPool = new WorkerPool(logger: $logger);

// 2. Add Prometheus group service
$workerPool->describeGroup(new PrometheusGroup);

// 3. Fill the worker pool with workers.
// We create a group of workers with the Reactor type, which are intended to handle incoming connections.
// The HttpReactor class is the entry point for the workers in this group.
// Please see the HttpReactor class for more details.
$workerPool->describeGroup(new WorkerGroup(
    entryPointClass: HttpReactorWithTelemetry::class,
    workerType: WorkerTypeEnum::REACTOR,
    minWorkers: 2,
    maxWorkers: 5
));

// 4. Run the worker pool
// Start the main loop of the worker pool
// Now the server is ready to accept incoming connections.
// Try http://127.0.0.1:9095/ in your browser.
$workerPool->run();
