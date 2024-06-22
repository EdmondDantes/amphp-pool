<?php
declare(strict_types=1);

namespace CT\AmpPool\Strategies\JobRunner;

use Amp\Cancellation;
use Amp\CompositeCancellation;
use Amp\Future;
use Amp\TimeoutCancellation;
use CT\AmpPool\Coroutine\Coroutine;
use CT\AmpPool\Coroutine\CoroutineInterface;
use CT\AmpPool\Coroutine\SchedulerInterface;

/**
 * Class JobRunnerScheduler
 *
 * Run jobs using a coroutine scheduler.
 *
 * @package CT\AmpPool\Strategies\JobRunner
 */
final readonly class JobRunnerScheduler implements JobRunnerInterface
{
    public function __construct(
        private SchedulerInterface  $scheduler,
        private JobHandlerInterface $handler,
        private int                 $maxJobCount        = 100,
        private int                 $defaultPriority    = 10,
        private int                 $maxAwaitAllTimeout = 0
    ) {}
    
    public function runJob(string $data, int $priority = null, int $weight = null, Cancellation $cancellation = null): Future
    {
        $handler                    = \WeakReference::create($this->handler);
        
        return $this->scheduler->run(new Coroutine(static function (CoroutineInterface $coroutine) use($handler, $data, $cancellation) {
            
            $handler                = $handler->get();
            
            if($handler instanceof JobHandlerInterface) {
                return $handler->handleJob($data, $coroutine, $cancellation);
            }
            
            return null;
            
        }, $priority ?? $this->defaultPriority), $cancellation);
    }
    
    public function canAcceptMoreJobs(): bool
    {
        return $this->scheduler->getCoroutinesCount() < $this->maxJobCount;
    }
    
    public function getJobCount(): int
    {
        return $this->scheduler->getCoroutinesCount();
    }
    
    public function awaitAll(Cancellation $cancellation = null): void
    {
        if($cancellation === null && $this->maxAwaitAllTimeout > 0) {
            $cancellation           = new TimeoutCancellation(
                $this->maxAwaitAllTimeout, 'JobRunnerAsync::awaitAll() timed out: '.$this->maxAwaitAllTimeout.'s'
            );
        } elseif ($this->maxAwaitAllTimeout > 0) {
            $cancellation           = new CompositeCancellation($cancellation, new TimeoutCancellation(
                $this->maxAwaitAllTimeout, 'JobRunnerAsync::awaitAll() timed out: '.$this->maxAwaitAllTimeout.'s'
            ));
        }
        
        $this->scheduler->awaitAll($cancellation);
    }
    
    public function stopAll(\Throwable $throwable = null): bool
    {
        $this->scheduler->stopAll($throwable);
        return true;
    }
}