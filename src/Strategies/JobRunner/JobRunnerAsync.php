<?php
declare(strict_types=1);

namespace CT\AmpPool\Strategies\JobRunner;

use Amp\Future;
use function Amp\async;

final class JobRunnerAsync         implements JobRunnerInterface
{
    private int $jobCount           = 0;
    private array $jobFutures       = [];
    
    public function __construct(private readonly JobHandlerInterface $handler) {}
    
    public function runJob(string $data, int $priority = null): Future
    {
        $handler                    = \WeakReference::create($this->handler);
        
        $future                     = async(static function () use ($data, $handler) {
            
            $handler                = $handler->get();
            
            if($handler instanceof JobHandlerInterface) {
                return $handler->handleJob($data);
            }
            
            return null;
        });
        
        $this->jobFutures[]         = $future;
        
        $this->jobCount++;

        $self                       = \WeakReference::create($this);
        
        $future->finally(static function() use ($self, $future) {
            
            $self                   = $self->get();
            
            if($self === null) {
                return;
            }
            
            $this->jobCount--;
            
            $index                  = \array_search($future, $self->jobFutures, true);
            
            if($index !== false) {
                unset($self->jobFutures[$index]);
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