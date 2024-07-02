# Getting started

## Workers Scheme

Workers are divided into three types:

* `reactor` - handle connections to the server
* `job` - process internal tasks
* `service` - A process that handles tasks, for example, from RabbitMQ

Multiple `reactors` can be created, each listening on different protocols/ports.
Similarly, different groups of `jobs` can be created to handle various types of tasks.


