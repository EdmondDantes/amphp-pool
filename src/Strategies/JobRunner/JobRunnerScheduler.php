<?php
declare(strict_types=1);

namespace CT\AmpPool\Strategies\JobRunner;

use Amp\Future;
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
    public function __construct(private SchedulerInterface $scheduler, private JobHandlerInterface $handler) {}
    
    public function runJob(string $data, int $priority = null): Future
    {
        $handler                    = \WeakReference::create($this->handler);
        
        return $this->scheduler->run(new Coroutine(static function (CoroutineInterface $coroutine) use($handler, $data) {
            
            $handler                = $handler->get();
            
            if($handler instanceof JobHandlerInterface) {
                return $handler->handleJob($data, $coroutine);
            }
            
            return null;
            
        }, $priority ?? 0));
    }
    
    public function getJobCount(): int
    {
        return $this->scheduler->getCoroutinesCount();
    }
    
    public function awaitAll(): void
    {
        $this->scheduler->awaitAll();
    }
    
    public function stopAll(\Throwable $throwable = null): bool
    {
        $this->scheduler->stopAll($throwable);
        return true;
    }
}