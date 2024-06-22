<?php
declare(strict_types=1);

namespace CT\AmpPool\JobIpc;

use CT\AmpPool\Exceptions\RemoteException;

final class JobSerializer            implements JobSerializerInterface
{
    final const int HEADER_LENGTH   = 16;
    
    public function createRequest(int $jobId, int $fromWorkerId, int $workerGroupId, string $data, int $priority = 0): string
    {
        return pack('V*', $jobId, $fromWorkerId, $workerGroupId, $priority).$data;
    }
    
    public function parseRequest(string $request): JobRequest
    {
        // Check minimum length
        if (\strlen($request) < self::HEADER_LENGTH) {
            throw new \InvalidArgumentException('Request is too short (less '.self::HEADER_LENGTH.' bytes)');
        }
        
        $buffer                     = \unpack('V*', substr($request, 0, self::HEADER_LENGTH));
        
        if(false === $buffer || \count($buffer) !== 4) {
            throw new \RuntimeException('Failed to unpack data for request');
        }
        
        [, $jobId, $fromWorkerId, $workerGroupId, $priority] = $buffer;
        $data                       = \substr($request, self::HEADER_LENGTH);
        
        return new JobRequest($jobId, $fromWorkerId, $workerGroupId, $priority, $data);
    }
    
    public function createResponse(int $jobId, int $fromWorkerId, int $workerGroupId, string|\Throwable $data): string
    {
        $isException                = $data instanceof \Throwable ? 1 : 0;
        
        if($data instanceof \Throwable) {
            
            if(false === $data instanceof RemoteException) {
                $data               = new RemoteException($data->getMessage(), 0, $data);
            }
            
            $data                   = \serialize($data);
        }
        
        return pack('V*', $jobId, $fromWorkerId, $workerGroupId, $isException).$data;
    }
    
    public function parseResponse(string $response): JobResponse
    {
        // Check minimum length
        if (strlen($response) < self::HEADER_LENGTH) {
            throw new \InvalidArgumentException('Response is too short (less '.self::HEADER_LENGTH.' bytes)');
        }
        
        $buffer                     = \unpack('V*', \substr($response, 0, self::HEADER_LENGTH));
        
        if(false === $buffer || count($buffer) !== 4) {
            throw new \RuntimeException('Failed to unpack data for response');
        }
        
        [, $jobId, $fromWorkerId, $workerGroupId, $isError] = $buffer;
        
        $data                       = \substr($response, self::HEADER_LENGTH);
        $exception                  = null;
        
        if($isError) {
            $exception              = \unserialize($data);
            $data                   = '';
            
            if(false === $exception) {
                $exception          = new RemoteException('Failed to unserialize response data');
            }
        }
        
        return new JobResponse($jobId, $fromWorkerId, $workerGroupId, $isError, $data, $exception);
    }
}