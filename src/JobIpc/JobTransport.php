<?php
declare(strict_types=1);

namespace CT\AmpServer\JobIpc;

final class JobTransport            implements JobTransportI
{
    final const int HEADER_LENGTH   = 16;
    
    public function createRequest(int $jobId, int $fromWorkerId, int $workerGroupId, string $data): string
    {
        return pack('V*', $jobId, $fromWorkerId, $workerGroupId, strlen($data)).$data;
    }
    
    public function parseRequest(string $request): JobRequest
    {
        // Check minimum length
        if (strlen($request) < self::HEADER_LENGTH) {
            throw new \InvalidArgumentException('Request is too short (less '.self::HEADER_LENGTH.' bytes)');
        }
        
        [$jobId, $fromWorkerId, $workerGroupId, $dataLength] = unpack('V*', substr($request, 0, self::HEADER_LENGTH));
        $data                       = substr($request, self::HEADER_LENGTH);
        
        return new JobRequest($jobId, $fromWorkerId, $workerGroupId, $dataLength, $data);
    }
    
    public function createResponse(int $jobId, int $fromWorkerId, int $workerGroupId, string $data): string
    {
        return pack('V*', $jobId, $fromWorkerId, $workerGroupId, strlen($data)).$data;
    }
    
    public function parseResponse(string $response): JobResponse
    {
        // Check minimum length
        if (strlen($response) < self::HEADER_LENGTH) {
            throw new \InvalidArgumentException('Response is too short (less '.self::HEADER_LENGTH.' bytes)');
        }
        
        [$jobId, $fromWorkerId, $workerGroupId, $dataLength] = unpack('V*', substr($response, 0, self::HEADER_LENGTH));
        $data                       = substr($response, self::HEADER_LENGTH);
        
        return new JobResponse($jobId, $fromWorkerId, $workerGroupId, $dataLength, $data);
    }
}