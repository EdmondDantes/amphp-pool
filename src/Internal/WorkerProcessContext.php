<?php
declare(strict_types=1);

namespace CT\AmpPool\Internal;

use Amp\Cancellation;
use Amp\CancelledException;
use Amp\CompositeCancellation;
use Amp\DeferredCancellation;
use Amp\DeferredFuture;
use Amp\ForbidCloning;
use Amp\ForbidSerialization;
use Amp\Future;
use Amp\Parallel\Context\Context;
use Amp\Parallel\Context\ProcessContext;
use Amp\Process\ProcessException;
use Amp\Sync\ChannelException;
use Amp\TimeoutCancellation;
use CT\AmpPool\Exceptions\RemoteException;
use CT\AmpPool\Exceptions\WorkerShouldBeStopped;
use CT\AmpPool\Internal\Messages\MessageLog;
use CT\AmpPool\Internal\Messages\MessagePingPong;
use CT\AmpPool\Internal\Messages\MessageShutdown;
use CT\AmpPool\Internal\Messages\WorkerStarted;
use CT\AmpPool\WorkerEventEmitterInterface;
use Revolt\EventLoop;
use const Amp\Process\IS_WINDOWS;
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
final class WorkerProcessContext implements \Psr\Log\LoggerInterface, \Psr\Log\LoggerAwareInterface
{
    use \Psr\Log\LoggerAwareTrait;
    use \Psr\Log\LoggerTrait;

    use ForbidCloning;
    use ForbidSerialization;

    protected int $pingTimeout          = 10;

    private int $lastActivity;

    private readonly Future $processFuture;
    private string          $watcher     = '';
    /**
     * Equals true if the client uses the worker exclusively.
     */
    private bool $isExclusive   = false;
    private array $transferredSockets = [];
    /**
     * When the worker process was terminated or should be terminated.
     * This descriptor will always be canceled if the child process has terminated.
     */
    private readonly DeferredCancellation $processCancellation;

    /**
     * @param positive-int $id
     * @param Context<mixed> $context
     */
    public function __construct(
        private readonly int                         $id,
        private readonly Context                     $context,
        private readonly Cancellation $workerCancellation,
        private readonly WorkerEventEmitterInterface $eventEmitter,
        private readonly DeferredFuture $startFuture,
        protected readonly int $processTimeout       = 5
    ) {
        $this->lastActivity         = \time();
        $this->processCancellation  = new DeferredCancellation;

        $processCancellation        = $this->processCancellation;
        $context                    = $this->context;
        $processTimeout             = $this->processTimeout;

        /**
         * The processFuture is a future that waits for the worker process to end.
         */
        $this->processFuture        = async(static function () use ($processCancellation, $context, $processTimeout): mixed {

            try {

                // First, wait for the end of the worker process
                // while waiting for the cancellation.
                try {
                    return $context->join($processCancellation->getCancellation());
                } catch (CancelledException) {
                    // Awaiting the worker process was interrupted by a cancellation not related to the worker process.
                }

                // Try to gracefully close the worker process if possible.
                try {
                    if(false === $context->isClosed()) {
                        $context->send(new MessageShutdown);
                    }
                } catch (ChannelException) {
                    // Ignore if the worker has already exited or the channel is closed
                }

                // Second, try to wait for the worker process to end
                return $context->join(
                    new TimeoutCancellation(
                        $processTimeout,
                        'The child process wait was interrupted by a timeout. The status is unknown.'
                    )
                );

            } catch (\Throwable $exception) {
                if(false === $processCancellation->isCancelled()) {
                    $processCancellation->cancel($exception);
                }

                //
                // HACK: On Windows, we got: Failed to read exit code from process wrapper
                // because sapi_windows_set_ctrl_handler broke the fibers.
                //
                if(IS_WINDOWS && $exception instanceof ProcessException) {
                    return null;
                }

                throw $exception;
            } finally {

                try {
                    // Close the context if it is not closed yet.
                    if(false === $context->isClosed()) {
                        $context->close();
                    }

                } finally {
                    if(false === $processCancellation->isCancelled()) {
                        $processCancellation->cancel();
                    }
                }
            }
        });
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getWorkerId(): int
    {
        return $this->id;
    }

    public function getCancellation(): Cancellation
    {
        return $this->processCancellation->getCancellation();
    }

    /**
     * The loop for receiving messages from the child process.
     * The method starts and freezes the execution thread until the process is completed.
     * The process can also be forcibly terminated or stopped due to a timeout.
     *
     * @throws \Throwable
     *
     */
    public function runWorkerLoop(): void
    {
        $cancellation               = new CompositeCancellation(
            $this->workerCancellation,
            $this->processCancellation->getCancellation()
        );

        try {

            $loopException          = null;

            try {
                /**
                 * There are several situations in which this loop will be interrupted:
                 *
                 * 1. The child process sent a NULL value - it completed successfully without errors.
                 * 2. workerCancellation triggered, meaning WorkerPool requires the process to be terminated.
                 * 3. processCancellation triggered, meaning the process was abruptly stopped or something else occurred.
                 *
                 */
                while (($message = $this->context->receive($cancellation)) !== null) {

                    $this->lastActivity = \time();

                    if($message instanceof RemoteException) {
                        throw $message;
                    }

                    if($message instanceof MessageLog) {
                        $this->logger?->log($message->level, $message->message, $message->context);
                        continue;
                    }

                    if($message instanceof WorkerStarted) {
                        if(false === $this->startFuture->isComplete()) {
                            $this->startFuture->complete($message);
                        }

                        continue;
                    }

                    $this->eventEmitter->emitWorkerEvent($message, $this->id);
                }

            } catch (\Throwable $loopException) {

                // Resolve the CancelledException
                if($loopException instanceof CancelledException && $loopException->getPrevious() !== null) {
                    $loopException  = $loopException->getPrevious();
                }

                if($loopException instanceof WorkerShouldBeStopped) {
                    $this->logger?->info('Worker #'.$this->id.' should be stopped: '.$loopException->getMessage());
                } else {
                    $this->logger?->error('Worker #'.$this->id.' error: '.$loopException->getMessage());
                }

            } finally {

                // Stop waiting for the worker process to end.
                if(false === $this->processCancellation->isCancelled()) {
                    $this->processCancellation->cancel();
                }
                
                if(false === $this->startFuture->isComplete()) {
                    $this->startFuture->complete();
                }
            }

        } finally {

            if($loopException === null) {
                $text               = 'Waiting for the worker #'.$this->id.' was interrupted due to a timeout ('.$this->processTimeout . '). '
                                    .'The child process properly closed the IPC connection.';
            } elseif ($loopException instanceof ChannelException) {

                /**
                 * When we receive a ChannelException, it means that the child process might have crashed.
                 * In this case, we wait for its termination and at the same time expect
                 * the result that the process returned.
                 */
                $text               = 'Waiting for the worker #'.$this->id
                                    .' to complete was interrupted due to a timeout ('.$this->processTimeout . ').'
                                    .' The connection with the worker process was lost. The state is undefined.';

            } elseif($loopException instanceof CancelledException) {

                if($loopException->getPrevious() !== null) {
                    $loopException  = $loopException->getPrevious();
                }

                $text               = 'Waiting for the worker #'.$this->id.' was interrupted due to a timeout ('.$this->processTimeout . '). '
                                    .'The child process was cancelled: '.$loopException->getMessage();

            } else {
                $text               = 'Waiting for the worker #'.$this->id.' was interrupted due to a timeout ('.$this->processTimeout . '). '
                                    .'Error occurred: '.$loopException->getMessage();
            }

            $processException       = null;

            try {
                $this->processFuture->await(new TimeoutCancellation($this->processTimeout));
            } catch (CancelledException) {
                $this->logger?->error($text);
            } catch (\Throwable $processException) {
            } finally {
                $this->close();

                if($processException !== null) {
                    throw $processException;
                }

                if($loopException !== null) {
                    throw $loopException;
                }
            }
        }
    }

    public function wasTerminated(): bool
    {
        return $this->processFuture->isComplete();
    }

    private function ping(): void
    {
        $isXDebug                   = \extension_loaded('xdebug') && \ini_get('xdebug.mode') === 'debug';

        $this->watcher              = EventLoop::repeat($this->pingTimeout / 2, weakClosure(function () use ($isXDebug): void {
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
        if(false === $this->startFuture->isComplete()) {
            $this->startFuture->complete();
        }

        if(false === $this->processCancellation->isCancelled()) {
            $this->processCancellation->cancel();
        }

        if($this->watcher !== '') {
            EventLoop::cancel($this->watcher);
            $this->watcher          = '';
        }

        if(false === $this->context->isClosed()) {
            $this->context->close();
        }
    }

    public function shutdown(): void
    {
        // Gracefully close the worker process.
        if(false === $this->processCancellation->isCancelled()) {
            $this->processCancellation->cancel();
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
