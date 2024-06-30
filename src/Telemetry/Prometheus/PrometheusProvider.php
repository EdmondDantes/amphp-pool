<?php
declare(strict_types=1);

namespace CT\AmpPool\Telemetry\Prometheus;

use CT\AmpPool\WorkersStorage\WorkersStorageInterface;
use CT\AmpPool\WorkersStorage\WorkerStateInterface;

final class PrometheusProvider
{
    public function __construct(
        private readonly WorkersStorageInterface $workersStorage
    ) {}
    
    public function render(): string
    {
        $metrics                    = [];
        $workers                    = $this->workersStorage->foreachWorkers();
        
        $metrics[] = '# TYPE worker_first_started_at gauge';
        foreach ($workers as $workerState) {
            $metrics[] = 'worker_first_started_at{' . $this->genLabels($workerState) . '} ' . $workerState->getFirstStartedAt() * 1000;
        }
        
        $metrics[] = '';
        
        $metrics[] = '# TYPE worker_start_at gauge';
        foreach ($workers as $workerState) {
            $metrics[] = 'worker_start_at{' . $this->genLabels($workerState) . '} ' . $workerState->getStartedAt() * 1000;
        }
        
        $metrics[] = '';
        
        $metrics[] = '# TYPE worker_finished_at gauge';
        foreach ($workers as $workerState) {
            $metrics[] = 'worker_finished_at{' . $this->genLabels($workerState) . '} ' . $workerState->getFinishedAt() * 1000;
        }
        
        $metrics[] = '';
        
        $metrics[] = '# TYPE worker_updated_at gauge';
        foreach ($workers as $workerState) {
            $metrics[] = 'worker_updated_at{' . $this->genLabels($workerState) . '} ' . $workerState->getUpdatedAt() * 1000;
        }
        
        $metrics[] = '';
        
        $metrics[] = '# TYPE worker_total_reloaded counter';
        foreach ($workers as $workerState) {
            $metrics[] = 'worker_total_reloaded{' . $this->genLabels($workerState) . '} ' . $workerState->getTotalReloaded();
        }
        
        $metrics[] = '';
        
        $metrics[] = '# TYPE worker_shutdown_errors counter';
        foreach ($workers as $workerState) {
            $metrics[] = 'worker_shutdown_errors{' . $this->genLabels($workerState) . '} ' . $workerState->getShutdownErrors();
        }
        
        $metrics[] = '';
        
        $metrics[] = '# TYPE worker_weight gauge';
        foreach ($workers as $workerState) {
            $metrics[] = 'worker_weight{' . $this->genLabels($workerState) . '} ' . $workerState->getWeight();
        }
        
        $metrics[] = '';
        
        $metrics[] = '# TYPE worker_php_memory_usage gauge';
        foreach ($workers as $workerState) {
            $metrics[] = 'worker_php_memory_usage{' . $this->genLabels($workerState) . '} ' . $workerState->getPhpMemoryUsage();
        }
        
        $metrics[] = '';
        
        $metrics[] = '# TYPE worker_php_memory_peak_usage gauge';
        foreach ($workers as $workerState) {
            $metrics[] = 'worker_php_memory_peak_usage{' . $this->genLabels($workerState) . '} ' . $workerState->getPhpMemoryPeakUsage();
        }
        
        $metrics[] = '';
        
        $metrics[] = '# TYPE worker_connections_accepted counter';
        foreach ($workers as $workerState) {
            $metrics[] = 'worker_connections_accepted{' . $this->genLabels($workerState) . '} ' . $workerState->getConnectionsAccepted();
        }
        
        $metrics[] = '# TYPE worker_connections_processed counter';
        foreach ($workers as $workerState) {
            $metrics[] = 'worker_connections_processed{' . $this->genLabels($workerState) . '} ' . $workerState->getConnectionsProcessed();
        }
        
        $metrics[] = '';
        
        $metrics[] = '# TYPE worker_connections_errors counter';
        foreach ($workers as $workerState) {
            $metrics[] = 'worker_connections_errors{' . $this->genLabels($workerState) . '} ' . $workerState->getConnectionsErrors();
        }
        
        $metrics[] = '';
        
        $metrics[] = '# TYPE worker_connections_rejected counter';
        foreach ($workers as $workerState) {
            $metrics[] = 'worker_connections_rejected{' . $this->genLabels($workerState) . '} ' . $workerState->getConnectionsRejected();
        }
        
        $metrics[] = '';
        
        $metrics[] = '# TYPE worker_connections_processing gauge';
        foreach ($workers as $workerState) {
            $metrics[] = 'worker_connections_processing{' . $this->genLabels($workerState) . '} ' . $workerState->getConnectionsProcessing();
        }
        
        $metrics[] = '';
        
        $metrics[] = '# TYPE worker_job_accepted counter';
        foreach ($workers as $workerState) {
            $metrics[] = 'worker_job_accepted{' . $this->genLabels($workerState) . '} ' . $workerState->getJobAccepted();
        }
        
        $metrics[] = '';
        
        $metrics[] = '# TYPE worker_job_processed counter';
        foreach ($workers as $workerState) {
            $metrics[] = 'worker_job_processed{' . $this->genLabels($workerState) . '} ' . $workerState->getJobProcessed();
        }
        
        $metrics[] = '';
        
        $metrics[] = '# TYPE worker_job_processing gauge';
        foreach ($workers as $workerState) {
            $metrics[] = 'worker_job_processing{' . $this->genLabels($workerState) . '} ' . $workerState->getJobProcessing();
        }
        
        $metrics[] = '';
        
        $metrics[] = '# TYPE worker_job_errors counter';
        foreach ($workers as $workerState) {
            $metrics[] = 'worker_job_errors{' . $this->genLabels($workerState) . '} ' . $workerState->getJobErrors();
        }
        
        $metrics[] = '';
        
        $metrics[] = '# TYPE worker_job_rejected counter';
        foreach ($workers as $workerState) {
            $metrics[] = 'worker_job_rejected{' . $this->genLabels($workerState) . '} ' . $workerState->getJobRejected();
        }
        
        return implode("\n", $metrics);
    }
    
    protected function genLabels(WorkerStateInterface $workerState): string
    {
        return 'worker_id="'.$workerState->getWorkerId().'", group_id="'.$workerState->getGroupId().'"';
    }
    
}