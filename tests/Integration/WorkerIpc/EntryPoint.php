<?php
declare(strict_types=1);

namespace CT\AmpPool\Integration\WorkerIpc;

use CT\AmpPool\Worker\WorkerEntryPointInterface;
use CT\AmpPool\Worker\WorkerInterface;
use CT\AmpPool\WorkerTypeEnum;

final class EntryPoint              implements WorkerEntryPointInterface
{
    public const string GROUP1      = 'group1';
    public const string GROUP2      = 'group2';
    
    private WorkerInterface $worker;
    
    public function initialize(WorkerInterface $worker): void
    {
        $this->worker               = $worker;
    }
    
    public function run(): void
    {
        $group                      = $this->worker->getWorkerGroup();

        if($group->getWorkerType() === WorkerTypeEnum::REACTOR) {
        
        } elseif ($group->getWorkerType() === WorkerTypeEnum::JOB) {
        
        } else {
            throw new \RuntimeException('Unknown Worker Type');
        }
    }
}