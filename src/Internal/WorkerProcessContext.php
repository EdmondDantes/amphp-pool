<?php
declare(strict_types=1);

namespace CT\AmpPool\Internal;

use Amp\Cancellation;
use Amp\CancelledException;
use Amp\DeferredCancellation;
use Amp\ForbidCloning;
use Amp\ForbidSerialization;
use Amp\Future;
use Amp\Parallel\Context\Context;
use Amp\Parallel\Context\ProcessContext;
use Amp\Sync\ChannelException;
use Amp\TimeoutCancellation;
use CT\AmpPool\Exceptions\RemoteException;
use CT\AmpPool\Internal\Messages\MessageLog;
use CT\AmpPool\Internal\Messages\MessagePingPong;
use CT\AmpPool\Internal\Messages\MessageShutdown;
use CT\AmpPool\Strategies\SocketStrategy\Unix\SocketPipeTransport;
use CT\AmpPool\Strategies\SocketStrategy\Windows\SocketListenerProvider;
use CT\AmpPool\WorkerEventEmitterInterface;
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
final class WorkerProcessContext        implements \Psr\Log\LoggerInterface, \Psr\Log\LoggerAwareInterface
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
     */
    public function __construct(
        private readonly int                  $id,
        private readonly Context              $context,
        private readonly DeferredCancellation $deferredCancellation,
        private readonly WorkerEventEmitterInterface $eventEmitter
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
    
    public function runWorkerLoop(): void
    {
        $cancellation               = $this->deferredCancellation->getCancellation();
        
        try {
            while (($message = $this->context->receive($cancellation)) !== null) {
                
                $this->lastActivity = \time();
                
                if($message instanceof RemoteException) {
                    throw $message;
                }
                
                if($message instanceof MessageLog) {
                    $this->logger?->log($message->level, $message->message, $message->context);
                    continue;
                }
                
                $this->eventEmitter->emitWorkerEvent($message, $this->id);
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
        
        if(false === $this->deferredCancellation->isCancelled()) {
            $this->deferredCancellation->cancel();
        }
    }
    
    public function shutdown(?Cancellation $cancellation = null): void
    {
        try {
            if (!$this->context->isClosed()) {
                try {
                    $this->context->send(new MessageShutdown);
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
    
    public function shutdownSoftly(): void
    {
        if (false === $this->context->isClosed()) {
            try {
                $this->context->send(new MessageShutdown(true));
            } catch (ChannelException) {
                // Ignore if the worker has already exited
            }
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