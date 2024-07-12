# Worker strategies

## Introduction

`Worker strategies` enable managing the behavior of a `WorkerPool`.
For each `group` of workers, behavior strategies can be unique.
Here, we will review the types of strategies and their variations.

## How-to strategies work?

Strategies are classes that implement specific behavior.
Strategies are owned by groups of `Workers`.
The WorkerPool uses an `IPC` (*Inter-Process Communication*) channel to pass information about all groups, 
including information about all strategies, to each `Worker` process.

You can think of this operation as analogous to `fork()` for a process. 
However, `WorkerPool` does not actually use `fork()`. 
This means that the `Worker group schema` is serialized and transmitted between processes in such a way 
that each process receives its identical copy.

After the `Worker process` receives the `group schema`, it begins initialization.
To do this, it passes all information about itself to each strategy within each group and allows the strategy to initialize. 
This results in strategy classes having their own copies in each `Worker process` and 
being able to influence their operation.

This also applies to the `Watcher process`, which also launches and initializes all strategies. 
Some strategies may operate as part of the `Watcher process`, while others may operate in `Worker processes`. 
Therefore, you might see code like this:

```php
$workerPool = $this->getWorkerPool();

if($workerPool === null) {
    return;
}

// Code for the Watcher process

```

or 

```php
$worker = $this->getSelfWorker();

if($worker === null) {
    return;
}

// Code for the Worker process    

```

It is also recommended to use the `WorkerStrategyAbstract` class as a base class for your strategies.

## Pickup Strategies

The `pickup strategy` is responsible for selecting a worker from the pool. 
Each time a worker attempts to send a Job for execution, 
the `pickupWorker` method is called, which should return the ID of the worker. 
By modifying this strategy, you can control how the load is distributed among Workers and optimize `CPU` usage 
in the best possible way.

### Round Robin Pickup Strategy

The `round robin pickup strategy` selects a worker in a round-robin fashion.

### Least Loaded Pickup Strategy

The `least loaded pickup strategy` selects a worker with the least number of tasks.

## Restart Strategy

The `restart strategy` is responsible for restarting a worker.

### Never Restart Strategy

The `never restart strategy` never restarts a worker.

### Always Restart Strategy

The `always restart strategy` always restarts a worker.

### Restart On Failure Strategy

The `restart on failure strategy` restarts a worker only if the worker fails.

## Scale Strategy

The `scale strategy` is responsible for scaling the number of workers.

### Scaling By Request

The `ScalingByRequest` scales the number of workers based on the minimum and maximum number of workers.
The strategy is based on the number of negative hits by the `PickupWorker` strategy. 
Each time the `PickupWorker` indicates that no available Workers are present, the `Scale strategy` is invoked. 
The `Scale strategy` can then decide whether to send a signal to add a Worker or not.

## Restart Strategy

The `restart strategy` is responsible for restarting a worker.
Possible strategies include:
* `RestartNever` - never restarts a worker.
* `RestartAlways` - always restarts a worker.
* `RestartWithLimiter` - restarts a worker only if the worker fails and the number of restarts is less than the limit.
* `RestartWithWindowLimiter` - restarts a worker only if the worker fails 
and the number of restarts within the time window is less than the limit.

## Socket Strategy

The Socket strategy handles the `TCP/IP` connections.
Currently, there are two strategies available, specific to different operating systems: `Windows` and `Unix`.

* The `Unix` strategy shares the `server socket` connection among all processes in the group.
* The `Windows` strategy shares the `client socket` among processes in the group.

It's worth noting that the `Windows strategy` can potentially be useful in Unix environments with some modifications. 
The `Unix strategy` is the most commonly used in modern applications that handle connections from multiple clients.

## JobExecutor and JobClient Strategies

The `JobExecutor strategy` is responsible for executing a job.
These strategies implement an interface for communication between workers to execute background tasks.

## Runner Strategy

The `runner strategy` is responsible for start the worker process.