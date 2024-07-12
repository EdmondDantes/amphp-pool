# Getting started

## Workers Scheme

The `WorkerPool` class is responsible for managing the lifecycle of worker processes, 
including starting, restarting, and monitoring. 
Worker processes are organized into groups, with each group capable of having its own configurations and strategies.

Before calling `run()` the `WorkerPool`,
you must define all worker groups and specify their respective strategies and parameters.
Only after these configurations are in place can the WorkerPool be initiated.

Workers are divided into three types:

* `reactor` - handle connections to the server
* `job` - process internal tasks
* `service` - A process that handles tasks, for example, from `RabbitMQ`

Example:

```php
// 1. Create a worker pool with a logger
$workerPool = new WorkerPool(logger: $logger);

// 2. Fill the worker pool with workers.
$workerPool->describeGroup(new WorkerGroup(
    entryPointClass: HttpReactor::class,
    workerType: WorkerTypeEnum::REACTOR    
));

// 3. Run the worker pool
$workerPool->applyGlobalErrorHandler();
$workerPool->run();
```

Once the `run()` method has been called, the worker groups cannot be modified.

## Worker Group options

The `WorkerGroup` class is responsible for defining the worker group configuration.
The following options are available:

* `entryPointClass` - The class that will be used as the entry point for the worker group.
* `workerType` - The type of worker group. The following types are available:
    * `WorkerTypeEnum::REACTOR` - A worker group that handles connections to the server.
    * `WorkerTypeEnum::JOB` - A worker group that processes internal tasks.
    * `WorkerTypeEnum::SERVICE` - A worker group that handles tasks from `RabbitMQ`. 
* `minWorkers` - The minimum number of workers in the group.
* `maxWorkers` - The maximum number of workers in the group.
* `groupName` - The name of the worker group.
* `runnerStrategy` - The strategy responsible for launching workers and initialization.
* `pickupStrategy` - The strategy used to pick up worker from the pool.
* `restartStrategy` - The strategy used to restart workers in the group.
* `scalingStrategy` - The strategy used to scale the number of workers in the group.
* `socketStrategy` - The strategy responsible for sharing connections between different processes, 
designed for Reactor-type Workers.
* `jobExecutor` - The strategy responsible for task execution.
* `jobClient` - The strategy that allows sending tasks for execution to other workers.
* `autoRestartStrategy` - The strategy allows restarting workers if they reach certain boundary conditions or exhaust their quota.

## Entry point class

The entry point class serves as the entry point for a worker process. 
It allows for initialization or additional specific actions if needed.

The class should implement the `WorkerEntryPointInterface` interface, which contains the following methods:
1. `initialize(WorkerInterface $worker): void` - This method is called when the worker is initialized.
2. `run(): void` - This method is called when the worker is started.

```php
use IfCastle\AmpPool\Strategies\JobExecutor\JobHandlerInterface;
use IfCastle\AmpPool\Worker\WorkerEntryPointInterface;
use IfCastle\AmpPool\Worker\WorkerInterface;

final class MyWorker implements WorkerEntryPointInterface
{
    private WorkerInterface $worker;

    public function initialize(WorkerInterface $worker): void
    {
        // Now worker is ready to work but not started events Loops...
        // we can change some state or do some initialization here
        $this->worker               = $worker;
    }

    public function run(): void
    {
        // Here Worker is started and ready to work.
        // We can add some logic here if needed.        
        $this->worker->awaitTermination();
        
        // The worker process will be stopped after run() return.
    }
}
```

The `WorkerInterface` interface allows you to interact with the worker process, worker state, strategies.

The worker process will be stopped as soon as the `run()` method completes 
normally or throws an exception.
If you want to simply wait for the worker to finish, 
you should use `$this->worker->awaitTermination()`.

## Reactor Worker

The `Reactor` worker is responsible for handling connections to the server.

Below, you can see an example of a reactor running an `AMPHP` `HTTP server`.

```php
/**
 * This class is the entry point of the reactor process,
 * which is designed to handle incoming connections.
 *
 * @package Examples\HttpServer
 */
final class HttpReactor implements WorkerEntryPointInterface
{
    private ?\WeakReference $worker = null;

    public function initialize(WorkerInterface $worker): void
    {
        // 1. This method receives a class that handles the abstraction of the Worker process.
        // The method is called before the run() method.
        $this->worker               = \WeakReference::create($worker);
    }

    public function run(): void
    {
        // The method is called after the initialize() method.

        // 1. Create a socket server (please see amp/http-server package for more details)

        // The workerStrategy provides the socket factory, which is used to create the server.
        // This is necessary because the socket is initially created in the parent process
        // and only then passed to the child process.

        $worker                     = $this->worker->get();

        if ($worker === null) {
            return;
        }

        $socketFactory              = $worker->getWorkerGroup()->getSocketStrategy()->getServerSocketFactory();
        $clientFactory              = new SocketClientFactory($worker->getLogger());
        $httpServer                 = new SocketHttpServer($worker->getLogger(), $socketFactory, $clientFactory);

        // 2. Expose the server to the network
        $httpServer->expose('127.0.0.1:9095');

        // 3. Handle incoming connections and start the server
        $httpServer->start(
            new ClosureRequestHandler(static function () use ($worker): Response {

                return new Response(
                    HttpStatus::OK,
                    [
                    'content-type' => 'text/plain; charset=utf-8',
                ],
                    'Hello, World! From worker id: '.$worker->getWorkerId()
                   .' and group id: '.$worker->getWorkerGroupId()
                );
            }),
            new DefaultErrorHandler(),
        );

        // 4. Await termination of the worker
        $worker->awaitTermination();

        // 5. Stop the HTTP server
        $httpServer->stop();
    }
}
```

## Job Worker

The `Job` worker is responsible for processing internal tasks.

Below, you can see an example of a job worker that processes tasks.

```php
final class JobWorker implements WorkerEntryPointInterface, JobHandlerInterface
{
    private WorkerInterface $worker;

    public function initialize(WorkerInterface $worker): void
    {
        $this->worker               = $worker;
        
        // Here we define the job handler for the jobs that will be executed by this worker.
        $worker->getWorkerGroup()->getJobExecutor()->defineJobHandler($this);
    }

    public function run(): void
    {
        $this->worker->awaitTermination();
    }

    // This method is called when a job is received by the worker.
    public function handleJob(
        string              $data,
        ?CoroutineInterface $coroutine = null,
        ?Cancellation       $cancellation = null
    ): mixed {
        return "Hello a job: $data\n";
    }
}
```

## Service Worker

A worker `service` is generally intended to perform specific tasks that are not related to receiving external connections from users or handling tasks. 
For example, a `Prometheus worker` allows retrieving the current state of the `WorkerPool` from shared memory.

Below, you can see an example of a service worker that processes tasks.

```php
final class ServiceWorker implements WorkerEntryPointInterface, JobHandlerInterface
{
    private WorkerInterface $worker;
    private string $callbackId;

    public function initialize(WorkerInterface $worker): void
    {
        $this->worker = $worker;        
    }

    public function run(): void
    {
        $this->callbackId = EventLoop::repeat(1000, function () {
            $this->worker->getLogger()->info('Service worker is running');
        });
        
        try {
            $this->worker->awaitTermination();
        } finally {
            EventLoop::cancel($this->callbackId);
        }      
    }
}
```

A service worker can also use job execution in other workers or run its own HttpServer to accept connections. 
A service worker is especially useful when you need additional functionality and want to ensure 
that it runs in only one process and is automatically restarted in case of issues.

## Setting Up Communication Between Reactor and Job Workers

If you want a group of `Reactor`-type workers to be able to send tasks to another group of `Job`-type workers, 
you must explicitly define the relationship between the groups.

To do this, you need to specify the `jobGroups` parameter in the `WorkerGroup` configuration:

```php

$workerPool->describeGroup(new WorkerGroup(
    entryPointClass: JobWorker::class,
    workerType: WorkerTypeEnum::JOB,
    groupName: 'JobWorker'
));

$workerPool->describeGroup(new WorkerGroup(
    entryPointClass: HttpReactorWithTelemetry::class,
    workerType: WorkerTypeEnum::REACTOR,
    // jobGroups - the list of groups that can accept tasks from this group
    jobGroups: ['JobWorker']
));
```

You can define multiple groups of workers that can accept jobs or use a single group of job workers 
to which several groups of reactors will send tasks, and so on.

The interaction scheme can be exactly what you need to solve your task.

## Pickup Strategy

The `PickupStrategy` is responsible for selecting a worker from the pool to process a task. 
`WorkerPool` provides you with several strategies to choose from for optimal worker selection. 
In most cases, the `PickupRoundRobin` or `PickupLeastJobs` strategy works well, 
as they allow efficient load distribution among workers. 

However, sometimes you need more. For example, you might want to execute long-running tasks 
in one group of workers and quick tasks in another. 
This can be useful if you need to control the maximum number of tasks of a specific type.

Please see the `IfCastle\AmpPool\Strategies\PickupStrategy` namespace for more details.

## How to use asynchronous programming inside workers

When writing code for the `WorkerPool` in an asynchronous style, 
it's important to remember that the code can be stopped at any moment and must be able to terminate correctly.

Coroutines should be periodically and properly stopped, kernel object waits should be correctly canceled, 
and resources should be freed and closed.

The `WorkerInterface` provides several ways to stop the code at the moment when the worker needs to be terminated. 
The `public function getAbortCancellation()` method returns a `Cancellation` object, 
which can be added to kernel object wait methods to stop execution upon triggering.

For watcher process, we should use `WorkerPoolInterface::getMainCancellation()` method.

```php
        try {
            while (($client = $this->server->accept($this->worker->getAbortCancellation())) !== null) {
                // ...
            }
        } catch (CancelledException) {
            // The worker is stopping
            // exit from the loop
        }
```

## Prometheus' metrics and Grafana dashboard

The `WorkerPool` provides a built-in `Prometheus` metrics server 
that allows you to monitor the state of the `WorkerPool` in real-time.

To enable the `Prometheus` server, you need to add the following code to the `WorkerPool` initialization:

1. Step. Add the `Prometheus` group to the `WorkerPool`.

```php
$workerPool->describeGroup(new PrometheusGroup);
```

By default, the `Prometheus` service will be available at `http://localhost:9091/metrics`.

2. Step. Add to prometheus.yml the following configuration:

```yaml
scrape_configs:
  - job_name: 'worker_pool'
    static_configs:
      - targets: ['localhost:9091']
```

3. Step. Import to Grafana the following dashboard JSON model: [Model](./grafana/model.json).

4. Step. Add to Grafana the Prometheus data source.

## Using PidFile and Cli commands: stop, restart

Server applications often cannot be run simultaneously in two instances, so they require a Pid file lock.
The `WorkerPool` provides a built-in `PidFile` option that allows you to store the process ID 
of the `WorkerPool` in a file.

```php
$workerPool = new WorkerPool(logger: $logger, pidFile: true);
```

When using the PidFile option, your application will be able to respond to stop and restart commands on `Unix` platforms. 
For example:

```shell
php app.php stop
php app.php restart
```

In this case, signals will be sent to the running application: `SIGTERM` for stop and `SIGUSR1` for restart.

## Windows Support

All the functionality of this library works on `Windows OS`, 
enabling the application to be used across different operating systems. However, there are a few known issues:

* `Windows` uses a different socket sharing model, which affects the implementation of the `TCP server`.
* `Windows` has a memory leak when using `Fiber` + `Socket` that cannot be bypassed.
* There is a minor bug when attempting to reopen a closed socket, which does not impact the application's operation.

Therefore, we do not recommend using `Windows` for production. 
However, you should not encounter any issues if you want to use this library for debugging purposes on `Windows`.