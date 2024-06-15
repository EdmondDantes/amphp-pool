<?php
declare(strict_types=1);

use Amp\Future;
use Amp\Sync\Channel;
use CT\AmpPool\Worker\WorkerEntryPointInterface;
use CT\AmpPool\Worker\Worker;
use CT\AmpPool\Exceptions\FatalWorkerException;
use CT\AmpPool\Exceptions\RemoteException;
use CT\AmpPool\WorkerGroupInterface;
use function Amp\async;

return static function (Channel $channel): void
{
    if (\function_exists('posix_setsid')) {
        // Allow accepting signals (like SIGINT), without having signals delivered to the watcher impact the cluster
        \posix_setsid();
    }
    
    try {
        // Read random IPC hub URI and associated key from a process channel.
        ['id' => $id, 'uri' => $uri, 'key' => $key, 'group' => $group, 'groupsScheme' => $groupsScheme]
                = $channel->receive();
        
        if(false === $group instanceof WorkerGroupInterface) {
            throw new Error('Invalid group type. Expected WorkerGroupInterface');
        }
        
        if(!is_array($groupsScheme)) {
            throw new Error('Invalid groups scheme. Expected array');
        }
        
    } catch (\Throwable $exception) {
        throw new FatalWorkerException('Could not connect to IPC socket', 0, $exception);
    }
    
    if (\function_exists('cli_set_process_title')) {
        \set_error_handler(static fn () => true);
        try {
            \cli_set_process_title($group->getGroupName().' worker #'.$id. ' group #'.$group->getWorkerGroupId());
        } finally {
            \restore_error_handler();
        }
    }
    
    $strategy                       = null;
    
    try {
        
        $entryPointClassName        = $group->getEntryPointClass();
        
        if (class_exists($entryPointClassName)) {
            $entryPoint             = new $entryPointClassName();
        } else {
            throw new FatalWorkerException('Entry point class not found: ' . $entryPointClassName);
        }
        
        if (false === $entryPoint instanceof WorkerEntryPointInterface) {
            throw new FatalWorkerException('Entry point class must implement WorkerEntryPointI');
        }
        
        $strategy                   = new Worker((int)$id, $channel, $key, $uri, $group, $groupsScheme);
        
        $entryPoint->initialize($strategy);
        $strategy->initWorker();
        
        $referenceStrategy          = WeakReference::create($strategy);
        $referenceEntryPoint        = WeakReference::create($entryPoint);
        
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
        $strategy?->stop();
    }
};