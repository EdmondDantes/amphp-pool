<?php
declare(strict_types=1);

namespace CT\AmpServer\JobIpc;

final class JobTransport            implements JobTransportI
{
    public function createRequest(int $jobId, int $fromWorkerId, int $workerGroupId, string $data): string
    {
        return pack('Q*', $jobId, $fromWorkerId, $workerGroupId, strlen($data)).$data;
    }
    
    public function parseRequest(string $request): JobRequest
    {
        // Check minimum length
        if (strlen($request) < 32) {
            throw new \InvalidArgumentException('Request is too short (less 32 bytes)');
        }
        
        [$jobId, $fromWorkerId, $workerGroupId, $dataLength] = unpack('Q*', substr($request, 0, 32));
        $data                       = substr($request, 24);
        
        return new JobRequest($jobId, $fromWorkerId, $workerGroupId, $dataLength, $data);
    }
    
    public function createResponse(int $jobId, int $fromWorkerId, int $workerGroupId, string $data): string
    {
        return pack('Q*', $jobId, $fromWorkerId, $workerGroupId, strlen($data)).$data;
    }
    
    public function parseResponse(string $response): JobResponse
    {
        // Check minimum length
        if (strlen($response) < 32) {
            throw new \InvalidArgumentException('Response is too short (less 32 bytes)');
        }
        
        [$jobId, $fromWorkerId, $workerGroupId, $dataLength] = unpack('Q*', substr($response, 0, 32));
        $data                       = substr($response, 32);
        
        return new JobResponse($jobId, $fromWorkerId, $workerGroupId, $dataLength, $data);
    }
}