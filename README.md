# Am PHP Cluster [![PHP Composer](https://github.com/EdmondDantes/ampCluster/actions/workflows/php-debug.yml/badge.svg)](https://github.com/EdmondDantes/ampCluster/actions/workflows/php-debug.yml)

Implementation of a process pool for handling `TCP/IP` connections similar to `Swoole`, 
with the ability to create workers of different types and interaction between them.

Workers are divided into two types:

* `reactWorker` - handle connections to the server
* `jobWorker` - process internal tasks

Multiple reactWorkers can be created, each listening on different protocols/ports. 
Similarly, different groups of jobWorkers can be created to handle various types of tasks.

## Support for the Windows platform
The project implements a method for distributing socket connections among processes for the Windows platform.

## Example
```php
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

$workerPool                         = new WorkerPool(reactorCount: 5, jobCount: 5, logger: $logger);
$workerPool->fillWorkersWith(WorkerEntryPoint::class);
$workerPool->run();
$workerPool->mainLoop();
```
