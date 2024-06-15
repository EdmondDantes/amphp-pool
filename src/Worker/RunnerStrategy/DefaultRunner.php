<?php
declare(strict_types=1);

namespace CT\AmpPool\Worker\RunnerStrategy;

use Amp\Parallel\Context\Context;
use Amp\Serialization\SerializationException;
use Amp\Sync\ChannelException;
use CT\AmpPool\Worker\WorkerStrategyAbstract;
use CT\AmpPool\WorkerGroupInterface;
use CT\AmpPool\WorkerTypeEnum;

final class DefaultRunner extends WorkerStrategyAbstract implements RunnerStrategyInterface
{
    public function getScript(): string|array
    {
        return __DIR__ . '/runner.php';
    }
    
    /**
     * @throws SerializationException
     * @throws ChannelException
     */
    public function sendPoolContext(Context $processContext, int $workerId, WorkerGroupInterface $group): string
    {
        $workerPool                 = $this->getWorkerPool();
        
        if($workerPool === null) {
            throw new \RuntimeException('Worker pool is not defined.');
        }
        
        $key                        = $workerPool->getIpcHub()->generateKey();
        
        $processContext->send([
            'id'                    => $workerId,
            'uri'                   => $workerPool->getIpcHub()->getUri(),
            'key'                   => $key,
            'group'                 => $group,
            'groupsScheme'          => $workerPool->getGroupsScheme(),
        ]);
        
        return $key;
    }
    
    public function shouldProvideSocketTransport(): bool
    {
        return $this->getWorkerGroup()->getWorkerType() === WorkerTypeEnum::REACTOR;
    }
}