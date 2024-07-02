# Worker strategies

## Introduction

`Worker strategies` enable managing the behavior of a `WorkerPool`.
For each `group` of workers, behavior strategies can be unique.
Here, we will review the types of strategies and their variations.

## Pickup Strategy

The `pickup strategy` is responsible for selecting a worker from the pool.

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

### Simple Scale Strategy

The `simple scale strategy` scales the number of workers based on the minimum and maximum number of workers.