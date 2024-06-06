<?php
declare(strict_types=1);

namespace CT\AmpServer\JobIpc;

/**
 * Interface JobTransportI
 *
 * This interface defines the methods that a job transport must implement.
 */
interface JobTransportI
{
    public function createRequest(int $jobId, int $fromWorkerId, int $workerGroupId, string $data): string;
    public function parseRequest(string $request): JobRequest;
    public function createResponse(int $jobId, int $fromWorkerId, int $workerGroupId, string $data): string;
    public function parseResponse(string $response): JobResponse;
}