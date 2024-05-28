<?php
declare(strict_types=1);

namespace CT\AmpServer;

use Amp\Cancellation;
use Amp\CancelledException;
use Amp\Cluster\ClusterWorkerMessage;
use Amp\DeferredCancellation;
use Amp\ForbidCloning;
use Amp\ForbidSerialization;
use Amp\Future;
use Amp\Parallel\Context\Context;
use Amp\Parallel\Context\ProcessContext;
use Amp\Pipeline\Queue;
use Amp\Serialization\SerializationException;
use Amp\Socket\ResourceSocket;
use Amp\Socket\Socket;
use Amp\Sync\ChannelException;
use Amp\TimeoutCancellation;
use CT\AmpServer\Messages\MessageJob;
use CT\AmpServer\Messages\MessageJobResult;
use CT\AmpServer\Messages\MessageLog;
use CT\AmpServer\Messages\MessagePingPong;
use CT\AmpServer\Messages\MessageReady;
use CT\AmpServer\Messages\MessageSocketFree;
use CT\AmpServer\Messages\MessageSocketListen;
use CT\AmpServer\Messages\MessageSocketTransfer;
use CT\AmpServer\SocketPipe\SocketListenerProvider;
use CT\AmpServer\SocketPipe\SocketPipeTransport;
use Revolt\EventLoop;
use function Amp\async;
use function Amp\weakClosure;

/**
 * Worker Process Context.
 * The process context is a structure located within the process that creates or is associated with worker processes.
 * The process context cannot be used within the worker process itself.
 *
 * @template-covariant TReceive
 * @template TSend
 */
class WorkerProcessContext          implements \Psr\Log\LoggerInterface, \Psr\Log\LoggerAwareInterface
{
    use \Psr\Log\LoggerAwareTrait;
    use \Psr\Log\LoggerTrait;
    
    use ForbidCloning;
    use ForbidSerialization;
    
    protected int $pingTimeout         = 10;
    protected int $timeoutCancellation = 5;
    
    private int $lastActivity;
    
    private readonly Future $joinFuture;
    private string $watcher     = '';
    private bool $isReady       = false;
    private bool  $isUsed       = false;
    private int $jobsCount      = 0;
    /**
     * Equals true if the client uses the worker exclusively.
     * @var bool
     */
    private bool $isExclusive   = false;
    private array $transferredSockets = [];
    
    /**
     * @param positive-int $id
     * @param Context<mixed> $context
     * @param Queue<ClusterWorkerMessage<TReceive, TSend>> $queue
     */
    public function __construct(
        private readonly int                  $id,
        private readonly Context              $context,
        private readonly SocketPipeTransport|SocketListenerProvider|null $socketTransport,
        private readonly Queue                $queue,
        private readonly DeferredCancellation $deferredCancellation
    ) {
        $this->lastActivity         = \time();
        $this->joinFuture           = async($this->context->join(...));
    }
    
    public function getContext(): Context
    {
        return $this->context;
    }
    
    public function getWorkerId(): int
    {
        return $this->id;
    }
    
    public function isReady(): bool
    {
        return $this->isReady;
    }
    
    public function isUsed(): bool
    {
        return $this->isUsed;
    }
    
    public function isExclusive(): bool
    {
        return $this->isExclusive;
    }
    
    public function getJobsCount(): int
    {
        return $this->jobsCount;
    }
    
    public function addTransferredSocket(string $socketId, ResourceSocket|Socket $socket): self
    {
        $this->transferredSockets[$socketId] = $socket;
        $this->isUsed           = true;
        $this->jobsCount++;
        
        return $this;
    }
    
    public function freeTransferredSocket(string $socketId = null): self
    {
        if($socketId === null) {
            return $this;
        }
        
        if(array_key_exists($socketId, $this->transferredSockets)) {
            $this->jobsCount--;
            $this->transferredSockets[$socketId]->close();
            unset($this->transferredSockets[$socketId]);
        }
        
        if($this->jobsCount < 0) {
            $this->jobsCount        = 0;
        }
        
        if($this->jobsCount === 0) {
            $this->isUsed           = false;
        }
        
        return $this;
    }
    
    /**
     * @throws SerializationException
     * @throws ChannelException
     */
    public function sendJob(mixed $data): void
    {
        $this->context->send(new MessageJob($data));
        $this->jobsCount++;
    }
    
    public function runWorkerLoop(): void
    {
        $cancellation               = $this->deferredCancellation->getCancellation();
        
        try {
            while ($message = $this->context->receive($cancellation)) {
                
                $this->lastActivity = \time();
                
                if($message instanceof MessageReady) {
                    $this->isReady = true;
                } elseif ($message instanceof MessageSocketListen) {
                    
                    if($this->socketTransport instanceof SocketListenerProvider) {
                        $this->socketTransport->listen($this->id, $message->address);
                    }
                    
                    $this->isReady  = true;
                    
                } elseif($message instanceof MessageSocketTransfer) {
                    $this->isReady  = true;
                } elseif($message instanceof MessageSocketFree) {
                    $this->isReady  = true;
                    $this->freeTransferredSocket($message->socketId);
                } elseif($message instanceof MessageLog) {
                    $this->logger?->log($message->level, $message->message, $message->context);
                } elseif($message instanceof MessageJobResult) {
                    $this->isReady  = true;
                    $this->jobsCount--;
                    
                    if($this->jobsCount < 0) {
                        $this->jobsCount = 0;
                    }
                }
            }
            
            $this->joinFuture->await(new TimeoutCancellation($this->timeoutCancellation));
        } catch (\Throwable $exception) {
            $this->joinFuture->ignore();
            throw $exception;
        } finally {
            $this->close();
        }
    }
    
    private function ping(): void
    {
        $isXDebug                   = \extension_loaded('xdebug') && \ini_get('xdebug.mode') === 'debug';
        
        $this->watcher              = EventLoop::repeat($this->pingTimeout / 2, weakClosure(function () use($isXDebug): void {
            if (false === $isXDebug && $this->lastActivity < (\time() - $this->pingTimeout)) {
                $this->close();
                return;
            }
            
            try {
                $this->context->send(new MessagePingPong);
            } catch (\Throwable) {
                $this->close();
            }
        }));
    }
    
    private function close(): void
    {
        if($this->watcher !== '') {
            EventLoop::cancel($this->watcher);
        }
        
        $this->context->close();
        $this->socketTransport?->close();
        $this->deferredCancellation->cancel();
    }
    
    public function shutdown(?Cancellation $cancellation = null): void
    {
        try {
            if (!$this->context->isClosed()) {
                try {
                    $this->context->send(null);
                } catch (ChannelException) {
                    // Ignore if the worker has already exited
                }
            }
            
            try {
                $this->joinFuture->await($cancellation);
            } catch (CancelledException) {
                // Worker did not die normally within a cancellation window
            }
        } finally {
            $this->close();
        }
    }
    
    public function log($level, $message, array $context = []): void
    {
        $context['id']              = $this->id;
        
        if ($this->context instanceof ProcessContext) {
            $context['pid']         = $this->context->getPid();
        }
        
        $this->logger?->log($level, $message, $context);
    }
}