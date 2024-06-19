<?php
declare(strict_types=1);

namespace CT\AmpPool\Worker\JobRunner;

use Amp\Future;
use function Amp\async;

final class JobRunnerSimple         implements JobRunnerInterface
{
    private int $jobCount = 0;
    private array $jobFutures = [];
    
    public function __construct(private readonly \Closure $handler) {}
    
    public function runJob(string $data, int $priority = null): Future
    {
        $future                     = async($this->handler, $data);
        
        $this->jobFutures[]         = $future;
        
        $this->jobCount++;
        
        $future->finally(function() use ($future) {
            
            $this->jobCount--;
            
            $index                  = \array_search($future, $this->jobFutures, true);
            
            if($index !== false) {
                unset($this->jobFutures[$index]);
            }
        });
        
        return $future;
    }
    
    public function getJobCount(): int
    {
        return $this->jobCount;
    }
    
    public function awaitAll(): void
    {
        Future\awaitAll($this->jobFutures);
    }
    
    public function stopAll(\Throwable $throwable = null): bool
    {
        return false;
    }
}