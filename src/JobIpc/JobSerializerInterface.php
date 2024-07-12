<?php
declare(strict_types=1);

namespace IfCastle\AmpPool\JobIpc;

/**
 * Interface JobTransportI.
 *
 * The interface is responsible for serializing and deserializing JOBs.
 */
interface JobSerializerInterface
{
    public function createRequest(int $jobId, int $fromWorkerId, int $workerGroupId, string $data, int $priority = 0, int $weight = 0): string;
    public function parseRequest(string $request): JobRequestInterface;
    public function createResponse(int $jobId, int $fromWorkerId, int $workerGroupId, string|\Throwable $data): string;
    public function parseResponse(string $response): JobResponseInterface;
}
