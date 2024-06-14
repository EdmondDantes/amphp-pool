<?php
declare(strict_types=1);

namespace CT\AmpCluster\JobIpc;

use Amp\ByteStream\StreamChannel;
use Amp\ByteStream\StreamException;
use Amp\Cancellation;
use Amp\DeferredFuture;
use Amp\ForbidCloning;
use Amp\ForbidSerialization;
use Amp\Future;
use Amp\Serialization\PassthroughSerializer;
use Amp\TimeoutCancellation;
use Amp\TimeoutException;
use CT\AmpCluster\Exceptions\NoWorkersAvailable;
use CT\AmpCluster\Exceptions\SendJobException;
use CT\AmpCluster\PoolState\PoolStateStorage;
use CT\AmpCluster\WorkerState\WorkersStateInfo;
use Revolt\EventLoop;
use function Amp\delay;
use function Amp\Socket\socketConnector;

/**
 * The class is responsible for sending JOBs to other workers.
 */
final class IpcClient
{
    use ForbidCloning;
    use ForbidSerialization;
    
    /**
     * @var StreamChannel[]
     */
    private array                 $workerChannels = [];
    private JobSerializerI|null   $jobTransport   = null;
    private WorkersStateInfo|null $workersInfo    = null;
    private PoolStateStorage|null $poolState      = null;
    /**
     * List of futures that are waiting for the result of the job with SocketId, and time when the job was sent
     * @var array [Future, int, int]
     */
    private array $resultsFutures   = [];
    private int $maxTryCount        = 3;
    private int $futureTimeout      = 60 * 10;
    private string $futureTimeoutCallbackId;
    
    /**
     * IpcClient constructor.
     *
     * @param int                 $workerId         Current worker ID
     * @param int                 $workerGroupId    Current worker group ID
     * @param JobSerializerI|null $jobSerializer    Job serializer
     * @param Cancellation|null   $cancellation     Cancellation
     * @param int                 $retryInterval    Retry interval for sending a job
     */
    public function __construct(
        private readonly int               $workerId,
        private readonly int               $workerGroupId = 0,
        JobSerializerI                     $jobSerializer = null,
        private readonly Cancellation|null $cancellation = null,
        private readonly int               $retryInterval = 1
    )
    {
        $this->workersInfo          = new WorkersStateInfo();
        $this->poolState            = new PoolStateStorage();
        $this->jobTransport         = $jobSerializer ?? new JobSerializer();
    }
    
    public function mainLoop(): void
    {
        $this->futureTimeoutCallbackId = EventLoop::repeat($this->futureTimeout / 2, $this->updateFuturesByTimeout(...));
    }
    
    /**
     * Send a job to the worker asynchronously in the separate fiber.
     * If $awaitResult equals True than method returns a Future that will be completed when the job is done.
     *
     * However, the duration of a Job should not exceed the JobTimeout. Therefore, if you want to perform very long tasks,
     * you should consider how to properly organize work between workers or increase the JobTimeout.
     *
     * Please note that if the Job-Worker process terminates unexpectedly, all Futures will be completed with an error.
     *
     * Every time the job should be sent maxTryCount times with a retryInterval between attempts.
     * If retryInterval equals 0, the method will throw an exception if it cannot send the job.
     *
     * @param string   $data
     * @param int      $workerGroupId
     * @param bool     $awaitResult
     * @param int|null $workerId
     *
     * @return Future|null
     */
    public function sendJob(string $data, int $workerGroupId, bool $awaitResult = false, int $workerId = null): Future|null
    {
        $deferred                   = null;
        
        if($awaitResult) {
            $deferred               = new DeferredFuture();
        }
        
        EventLoop::queue($this->sendJobImmediately(...), $data, $workerGroupId, $deferred, $workerId);
        
        return $deferred?->getFuture();
    }
    
    /**
     * Try to send a job to the worker immediately in the current fiber.
     *
     * @param string              $data
     * @param int                 $workerGroupId
     * @param bool|DeferredFuture $awaitResult
     * @param int|null            $workerId
     *
     * @return Future|null
     * @throws \Throwable
     */
    public function sendJobImmediately(string $data, int $workerGroupId, bool|DeferredFuture $awaitResult = false, int $workerId = null): Future|null
    {
        $tryCount                   = 0;
        $ignoreWorkers              = [];
        
        if($awaitResult instanceof DeferredFuture) {
            $deferred               = $awaitResult;
        } else {
            $deferred               = $awaitResult ? new DeferredFuture() : null;
        }
        
        while($tryCount < $this->maxTryCount) {
            try {
                $socketId           = $this->tryToSendJob($data, $workerGroupId, $workerId, $ignoreWorkers, $deferred);
                
                if($deferred !== null) {
                    $this->resultsFutures[spl_object_id($deferred)] = [$deferred, $socketId, time()];
                    return $deferred->getFuture();
                } else {
                    return null;
                }
                
            } catch (NoWorkersAvailable $exception) {
                
                if($this->retryInterval <= 0) {
                    $deferred?->complete($exception);
                    throw $exception;
                } else {
                    $tryCount++;
                    // suspend the current task for a while
                    delay((float)$this->retryInterval, true, $this->cancellation);
                }
                
            } catch (StreamException) {
                $tryCount++;
                $ignoreWorkers[]    = $workerId;
            }
        }
        
        if($deferred !== null) {
            $deferred->complete(new SendJobException($workerGroupId, $this->maxTryCount));
            return $deferred->getFuture();
        }
        
        throw new SendJobException($workerGroupId, $this->maxTryCount);
    }
    
    private function tryToSendJob(string $data, int $workerGroupId, int $workerId = null, array $ignoreWorkers = [], DeferredFuture $deferred = null): int
    {
        $foundedWorkerId            = $this->pickupWorker($workerGroupId, $workerId, $ignoreWorkers);
        
        if($foundedWorkerId === null) {
            throw new NoWorkersAvailable($workerGroupId);
        }
        
        $channel                    = $this->getWorkerChannel($foundedWorkerId);
        $jobId                      = $deferred !== null ? spl_object_id($deferred) : 0;
        
        try {
            $channel->send($this->jobTransport->createRequest($jobId, $this->workerId, $workerGroupId, $data));
        } catch (\Throwable $exception) {
            $deferred->complete($exception);
            throw $exception;
        }
        
        return spl_object_id($channel);
    }
    
    public function __destruct()
    {
        $this->close();
    }
    
    public function close(): void
    {
        EventLoop::cancel($this->futureTimeoutCallbackId);
        
        $channels                   = $this->workerChannels;
        $this->workerChannels       = [];
        
        foreach($channels as $channel) {
            try {
                // Close connection gracefully
                $channel->send(IpcServer::CLOSE_HAND_SHAKE);
                $channel->close();
            } catch (\Throwable) {
            }
        }
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
    
    private function getWorkerChannel(int $workerId): StreamChannel
    {
        if(array_key_exists($workerId, $this->workerChannels)) {
            return $this->workerChannels[$workerId];
        }
        
        $this->workerChannels[$workerId] = $this->createWorkerChannel($workerId);
        
        EventLoop::queue($this->readLoop(...), $workerId);
        
        return $this->workerChannels[$workerId];
    }
    
    private function createWorkerChannel(int $workerId): StreamChannel
    {
        $connector                  = socketConnector();
        
        $client                     = $connector->connect(
            IpcServer::getSocketAddress($workerId), cancellation: new TimeoutCancellation(5)
        );
        
        $client->write(IpcServer::HAND_SHAKE);
        
        return new StreamChannel($client, $client, new PassthroughSerializer);
    }
    
    private function readLoop(int $workerId): void
    {
        $channel                    = $this->workerChannels[$workerId] ?? null;
        
        if($channel === null) {
            return;
        }
        
        try {
            while (($data = $channel->receive($this->cancellation)) !== null) {
                
                $response           = $this->jobTransport->parseResponse($data);
                
                if(array_key_exists($response->jobId, $this->resultsFutures)) {
                    [$deferred, ] = $this->resultsFutures[$response->jobId];
                    unset($this->resultsFutures[$response->jobId]);
                    $deferred->complete($response->data);
                }
            }
        } catch (\Throwable $exception) {
            
            unset($this->workerChannels[$workerId]);
            
            try {
                $channel->send(IpcServer::CLOSE_HAND_SHAKE);
                $channel->close();
            } catch (\Throwable) {
            }
        }
    }
    
    private function updateFuturesByTimeout(): void
    {
        $currentTime                = time();
        
        foreach($this->resultsFutures as $id => [$deferred, $socketId, $time]) {
            if($currentTime - $time > $this->futureTimeout) {
                unset($this->resultsFutures[$id]);
                $deferred->error(new TimeoutException('Future timeout: ' . $this->futureTimeout));
            }
        }
    }
}