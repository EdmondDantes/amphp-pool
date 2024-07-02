# Getting started

## Workers Scheme

Workers are divided into three types:

* `reactWorker` - handle connections to the server
* `jobWorker` - process internal tasks
* `serviceWorker` - A process that handles tasks, for example, from RabbitMQ

Multiple reactWorkers can be created, each listening on different protocols/ports.
Similarly, different groups of jobWorkers can be created to handle various types of tasks.
