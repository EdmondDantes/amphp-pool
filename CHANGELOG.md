# Changelog

## [1.0.0] - [Unreleased]

### Fixed

- Fixed socket waiting when the server is about to shut down. 
The issue remains for `UNIX` and is caused by the architecture of the `AMPHP` `httpserver`. 

### Added

- Added a component for monitoring the summary application state: `start time`, `number of restarts`, `uptime`.

### Changed

- Refined the FLOW of error analysis for the running Worker process.

## [0.9.0] - 2024-07-01 [Pre-release]

### Added

- Basic architecture for a Worker Pool library
- Worker groups/scheme functionality
- Worker strategies: `Pickup`, `Restart`, `Scale`, `Runner`, `JobExecutor`, `Socket` 
- `JobIpc` communication
- `CoroutineScheduler` component for scheduling tasks
- `WorkersStorage` shared memory component 
- Windows socket transfer support for Reactor-worker.
- Prometheus metrics
