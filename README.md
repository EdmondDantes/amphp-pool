# AmPHP Pool [![PHP Composer](https://github.com/EdmondDantes/amphp-pool/actions/workflows/php.yml/badge.svg)](https://github.com/EdmondDantes/amphp-pool/actions/workflows/php.yml)

Middle-level library for creating **Stateful** **Asynchronous** server-side applications
using the **pure PHP** and [AMPHP Library](https://github.com/amphp) ![AMPHP](https://avatars.githubusercontent.com/u/8865682?s=50&v=4)

* without *additional extensions* (such as `Swoole`)
* without auxiliary tools from *other* programming languages (such as `Go` + `Roadrunner`)

## Features

* Workers for handling connections and background tasks (**jobs**), which are restarted and scaled on demand.
* Support for different `types` and `groups` of Workers with varying behaviors. 
* Strategies for `restarting`, `scaling`, and `pickuping` Workers for load distribution.
* Execution of **Jobs** based on `priority` and `weight` (*weight being an estimate of resource consumption*).
* `Coroutine Scheduler` for distributing a load among long-running background jobs.
* Support for telemetry and statistics with **Prometheus** + **Grafana**.
* Full support for **Windows**.

## Installation

```bash
composer require ifcastle/amphp-pool
```

## Example

```php
<?php
declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use CT\AmpPool\WorkerGroup;
use CT\AmpPool\WorkerPool;
use CT\AmpPool\WorkerTypeEnum;
use Examples\HttpServer\HttpReactor;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

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
    minWorkers: 1
));

// 3. Run the worker pool
// Start the main loop of the worker pool
// Now the server is ready to accept incoming connections.
// Try http://127.0.0.1:9095/ in your browser.
$workerPool->run();
```
