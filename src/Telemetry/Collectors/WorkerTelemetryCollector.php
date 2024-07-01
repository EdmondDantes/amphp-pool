<?php
declare(strict_types=1);

namespace CT\AmpPool\Telemetry\Collectors;

use CT\AmpPool\WorkersStorage\WorkerStateInterface;

class WorkerTelemetryCollector      implements ConnectionCollectorInterface, JobCollectorInterface
{
    public function __construct(private readonly WorkerStateInterface $workerState, private readonly int $updateInterval = 1) {}
    
    public function flushTelemetry(): void
    {
        $this->workerState->updateConnectionsSegment()->updateJobSegment();
    }
    
    public function connectionAccepted(): void
    {
        $this->workerState->incrementConnectionsAccepted();
    }
    
    public function connectionProcessing(): void
    {
        $this->workerState->incrementConnectionsProcessing();
    }
    
    public function connectionUnProcessing(bool $withError = false): void
    {
        $this->workerState->decrementConnectionsProcessing();
        $this->workerState->incrementConnectionsProcessed();
        
        if($withError) {
            $this->workerState->incrementConnectionsErrors();
        }
    }
    
    public function connectionProcessed(): void
    {
        $this->workerState->incrementConnectionsProcessed();
    }
    
    public function connectionError(): void
    {
        $this->workerState->incrementConnectionsErrors();
    }
    
    public function jobAccepted(): void
    {
        $this->workerState->incrementJobAccepted();
    }
    
    public function jobProcessed(): void
    {
        $this->workerState->incrementJobProcessed();
    }
    
    public function jobError(): void
    {
        $this->workerState->incrementJobErrors();
    }
    
    public function jobRejected(): void
    {
        $this->workerState->incrementJobRejected();
    }
    
    public function jobProcessing(): void
    {
        $this->workerState->incrementJobProcessing();
    }
    
    public function jobUnProcessing(bool $withError = false): void
    {
        $this->workerState->decrementJobProcessing();
        $this->workerState->incrementJobProcessed();
        
        if($withError) {
            $this->workerState->incrementJobErrors();
        }
    }
}