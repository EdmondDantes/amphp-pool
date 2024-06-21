<?php
declare(strict_types=1);

namespace CT\AmpPool\Strategies\JobRunner;

use Amp\Future;
use CT\AmpPool\Coroutine\Coroutine;

final class JobCoroutineScheduler implements JobRunnerInterface
{
    public function __construct(private readonly \Closure $handler) {}
    
    public function runJob(string $data, int $priority = null): Future
    {
        $handler = $this->handler;
        
        return Coroutine::run(static function () use ($handler, $data) {
            return $handler($data);
        }, $priority);
    }
    
    public function getJobCount(): int
    {
        // TODO: Implement getJobCount() method.
    }
    
    public function awaitAll(): void
    {
        // TODO: Implement awaitAll() method.
    }
    
    public function stopAll(\Throwable $throwable = null): bool
    {
        // TODO: Implement stopAll() method.
    }
}