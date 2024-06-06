<?php
declare(strict_types=1);

namespace CT\AmpServer\JobIpc;

use Amp\ByteStream\StreamException;
use Amp\Cancellation;
use Amp\DeferredFuture;
use Amp\ForbidCloning;
use Amp\ForbidSerialization;
use Amp\Future;
use Amp\Socket\Socket;
use Amp\TimeoutCancellation;
use CT\AmpServer\Exceptions\NoAvailableWorkers;
use CT\AmpServer\Exceptions\SendJobException;
use CT\AmpServer\PoolState\PoolStateStorage;
use CT\AmpServer\WorkerState\WorkersStateInfo;
use Revolt\EventLoop;
use function Amp\Socket\socketConnector;

final class IpcClient
{
    use ForbidCloning;
    use ForbidSerialization;
    
    private array $workerSockets    = [];
    private JobTransportI|null $jobTransport      = null;
    private WorkersStateInfo|null $workersInfo    = null;
    private PoolStateStorage|null $poolState      = null;
    private array $resultsFutures = [];
    private int $maxTryCount        = 3;
    
    public function __construct(
        private readonly int $workerId,
        private readonly int $workerGroupId = 0,
        JobTransportI $jobTransport = null,
        private Cancellation|null $cancellation = null
    )
    {
        if($this->cancellation === null) {
            $this->cancellation     = new TimeoutCancellation(5);
        }
        
        $this->workersInfo          = new WorkersStateInfo();
        $this->poolState            = new PoolStateStorage();
        $this->jobTransport         = $jobTransport ?? new JobTransport();
    }
    
    public function sendJob(string $data, int $workerGroupId, bool $awaitResult = false, int $workerId = null): Future|null
    {
        $deferred                   = null;
        
        if($awaitResult) {
            $deferred               = new DeferredFuture();
        }
        
        EventLoop::queue($this->sendJobImmediately(...), $data, $workerGroupId, $deferred, $workerId);
        
        return $deferred?->getFuture();
    }
    
    public function sendJobImmediately(string $data, int $workerGroupId, bool $awaitResult = false, int $workerId = null): Future|null
    {
        $tryCount                   = 0;
        $ignoreWorkers              = [];
        $deferred                   = $awaitResult ? new DeferredFuture() : null;
        
        while($tryCount < $this->maxTryCount) {
            try {
                $this->tryToSendJob($data, $workerGroupId, $workerId, $ignoreWorkers, $deferred);
                
                if($deferred !== null) {
                    $this->resultsFutures[spl_object_id($deferred)] = $deferred;
                    return $deferred->getFuture();
                } else {
                    return null;
                }
                
            } catch (NoAvailableWorkers $exception) {
                $deferred?->complete($exception);
                throw $exception;
            } catch (StreamException) {
                $tryCount++;
                $ignoreWorkers[] = $workerId;
            }
        }
        
        $deferred?->complete();
        throw new SendJobException($workerGroupId, $this->maxTryCount);
    }
    
    private function tryToSendJob(string $data, int $workerGroupId, int $workerId = null, array $ignoreWorkers = [], DeferredFuture $deferred = null): void
    {
        $foundedWorkerId            = $this->pickupWorker($workerGroupId, $workerId, $ignoreWorkers);
        
        if($foundedWorkerId === null) {
            throw new NoAvailableWorkers($workerGroupId);
        }
        
        $socket                     = $this->getWorkerSocket($foundedWorkerId);
        $jobId                      = $deferred !== null ? spl_object_id($deferred) : 0;
        
        try {
            $socket->write($this->jobTransport->createRequest($jobId, $this->workerId, $workerGroupId, $data));
        } catch (\Throwable $exception) {
            $deferred->complete($exception);
            throw $exception;
        }
    }
    
    public function __destruct()
    {
    }
    
    private function pickupWorker(int $workerGroupId, int $workerId = null, array $ignoreWorkers = []): int|null
    {
        if($this->workerId === $workerId) {
            throw new \RuntimeException('Worker cannot send job to itself');
        }
        
        if($this->workerGroupId === $workerGroupId) {
            throw new \RuntimeException('Worker cannot send job to the same group');
        }
        
        if($workerId !== null) {
            return $workerId;
        }
        
        [$lowestWorkerId, $highestWorkerId] = $this->poolState->findGroupInfo($workerGroupId);
        
        $lastJobCount               = 0;
        $lastWorkerId               = null;
        
        for($workerId = $lowestWorkerId; $workerId <= $highestWorkerId; $workerId++) {
            $workerState            = $this->workersInfo->getWorkerState($workerId);
            
            if($workerState === null || false === $workerState->isReady || in_array($workerId, $ignoreWorkers, true)) {
                continue;
            }
            
            if($workerState->jobCount === 0) {
                return $workerId;
            }
            
            if($workerState->jobCount < $lastJobCount) {
                $lastJobCount       = $workerState->jobCount;
                $lastWorkerId       = $workerId;
            }
        }
        
        return $lastWorkerId;
    }
    
    private function getWorkerSocket(int $workerId): Socket
    {
        if(array_key_exists($workerId, $this->workerSockets)) {
            return $this->workerSockets[$workerId];
        }
        
        $this->workerSockets[$workerId] = $this->createConnectToWorker($workerId);
        
        return $this->workerSockets[$workerId];
    }
    
    private function createConnectToWorker(int $workerId): Socket
    {
        $connector                  = socketConnector();
        
        $client                     = $connector->connect(
            IpcServer::getSocketAddress($workerId), cancellation: $this->cancellation
        );
        
        $client->write(IpcServer::HAND_SHAKE);
        
        return $client;
    }
}