<?php
declare(strict_types=1);

namespace CT\AmpPool\Strategies\RunnerStrategy;

use Amp\Future;
use Amp\Parallel\Context\Context;
use Amp\Serialization\SerializationException;
use Amp\Sync\Channel;
use Amp\Sync\ChannelException;
use CT\AmpPool\Exceptions\FatalWorkerException;
use CT\AmpPool\Exceptions\RemoteException;
use CT\AmpPool\Strategies\WorkerStrategyAbstract;
use CT\AmpPool\Worker\Worker;
use CT\AmpPool\Worker\WorkerEntryPointInterface;
use CT\AmpPool\WorkerGroupInterface;
use CT\AmpPool\WorkerTypeEnum;
use function Amp\async;

class DefaultRunner extends WorkerStrategyAbstract implements RunnerStrategyInterface
{
    /**
     * It's the entry point for the worker process.
     *
     * @param Channel $channel
     *
     * @return void
     *
     * @throws \Throwable
     */
    public static function processEntryPoint(Channel $channel): void
    {
        if (\function_exists('posix_setsid')) {
            // Allow accepting signals (like SIGINT), without having signals delivered to the watcher impact the cluster
            \posix_setsid();
        }
        
        try {
            ['id' => $id, 'uri' => $uri, 'key' => $key, 'group' => $group, 'groupsScheme' => $groupsScheme]
                = self::readWorkerMetadata($channel);
            
        } catch (\Throwable $exception) {
            throw new FatalWorkerException('Could not connect to IPC socket', 0, $exception);
        }

        self::setProcessTitle($group->getGroupName().' worker #'.$id. ' group #'.$group->getWorkerGroupId());
        
        $worker                     = null;
        
        try {
            
            $entryPointClassName    = $group->getEntryPointClass();
            
            if (class_exists($entryPointClassName)) {
                $entryPoint         = new $entryPointClassName();
            } else {
                throw new FatalWorkerException('Entry point class not found: ' . $entryPointClassName);
            }
            
            if (false === $entryPoint instanceof WorkerEntryPointInterface) {
                throw new FatalWorkerException('Entry point class must implement WorkerEntryPointI');
            }
            
            $worker                 = new Worker((int)$id, $channel, $key, $uri, $group, $groupsScheme);
            
            $entryPoint->initialize($worker);
            $worker->initWorker();
            
            $referenceStrategy      = \WeakReference::create($worker);
            $referenceEntryPoint    = \WeakReference::create($entryPoint);
            
            /** @psalm-suppress InvalidArgument */
            Future\await([
                async(static function () use ($referenceStrategy): void {
                    $referenceStrategy->get()?->mainLoop();
                }),
                async(static function () use ($referenceEntryPoint): void {
                    $referenceEntryPoint->get()?->run();
                }),
            ]);
            
            // Normally, the worker process will exit when the IPC channel is closed.
            $channel->send(null);
            
        } catch (\Throwable $exception) {
            
            if(false === $exception instanceof RemoteException) {
                // Make sure that the exception is a FatalWorkerException
                $exception = new FatalWorkerException('Worker encountered a fatal error', 0, $exception);
            }
            
            $channel->send($exception);
            
            throw $exception;
        } finally {
            $worker?->stop();
        }
    }
    
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
    
    public static function readWorkerMetadata(Channel $channel): array
    {
        // Read random IPC hub URI and associated key from a process channel.
        $data                   = $channel->receive();
        
        if(empty($data)) {
            throw new FatalWorkerException('Could not read IPC data from channel');
        }
        
        foreach (['id', 'uri', 'key', 'group', 'groupsScheme'] as $key) {
            if (false === array_key_exists($key, $data)) {
                throw new FatalWorkerException('Invalid IPC data received. Expected key: '.$key);
            }
        }
        
        if(false === $data['group'] instanceof WorkerGroupInterface) {
            throw new \Error('Invalid group type. Expected WorkerGroupInterface');
        }
        
        if(!is_array($data['groupsScheme'])) {
            throw new \Error('Invalid groups scheme. Expected array');
        }
        
        return $data;
    }
    
    public static function setProcessTitle(string $title): void
    {
        if (\function_exists('cli_set_process_title')) {
            
            \set_error_handler(static fn () => true);
            
            try {
                \cli_set_process_title($title);
            } finally {
                \restore_error_handler();
            }
        }
    }
}