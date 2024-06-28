# Am PHP Pool [![PHP Composer](https://github.com/EdmondDantes/ampCluster/actions/workflows/php-debug.yml/badge.svg)](https://github.com/EdmondDantes/ampCluster/actions/workflows/php.yml)

Implementation of a process pool for handling `TCP/IP` connections similar to `Swoole`, 
with the ability to create workers of different types and interaction between them.

Workers are divided into two types:

* `reactWorker` - handle connections to the server
* `jobWorker` - process internal tasks
* `serviceWorker` - A process that handles tasks, for example, from RabbitMQ

Multiple reactWorkers can be created, each listening on different protocols/ports. 
Similarly, different groups of jobWorkers can be created to handle various types of tasks.

## Support for the Windows platform
The project implements a method for distributing socket connections among processes for the Windows platform.

## Example

```php
<?php
declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

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
    minWorkers: 1
));

// 3. Run the worker pool
// Start the main loop of the worker pool
// Now the server is ready to accept incoming connections.
// Try http://127.0.0.1:9095/ in your browser.
$workerPool->run();
```
