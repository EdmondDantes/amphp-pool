<?php
declare(strict_types=1);

namespace CT\AmpCluster\Worker\PickupStrategy;

use CT\AmpCluster\Worker\WorkerDescriptor;
use CT\AmpCluster\WorkerPoolInterface;
use CT\AmpCluster\WorkerTypeEnum;

/**
 * The class implements the strategy of selecting workers in a round-robin manner
 */
final class PickupRoundRobin implements PickupStrategyInterface
{
    /**
     * @var array<string, array<WorkerDescriptor>>
     */
    private array $poolByType     = [];
    
    public function __construct(private readonly WorkerPoolInterface $workerPool) {}
    
    public function pickupWorker(WorkerTypeEnum $workerType = null, array $possibleWorkers = null): ?WorkerDescriptor
    {
        $type                       = $workerType?->value ?? '';
        
        if(false === array_key_exists($type, $this->poolByType) || empty($this->poolByType[$type])) {
            $this->initPool($workerType, $possibleWorkers);
        }

        if(empty($this->poolByType[$type])) {
            return null;
        }
        
        return array_shift($this->poolByType[$type]);
    }
    
    private function initPool(WorkerTypeEnum $workerType = null, array $possibleWorkers = null): void
    {
        $type                       = $workerType?->value ?? '';
        
        $pool                       = [];
        
        foreach ($this->workerPool->getWorkers() as $worker) {
            if ($type !== null && $worker->type !== $type) {
                continue;
            }
            
            if ($possibleWorkers !== null && false === in_array($worker->id, $possibleWorkers)) {
                continue;
            }
            
            if(false === $worker->getWorker()->isReady()) {
                continue;
            }
            
            $pool[]                 = $worker;
        }
        
        $this->poolByType[$type]    = $pool;
    }
}
