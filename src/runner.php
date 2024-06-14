<?php
declare(strict_types=1);

use Amp\Future;
use Amp\Sync\Channel;
use CT\AmpCluster\Worker\WorkerEntryPointInterface;
use CT\AmpCluster\Worker\Worker;
use CT\AmpCluster\Exceptions\FatalWorkerException;
use function Amp\async;

return static function (Channel $channel): void
{
    if (\function_exists('posix_setsid')) {
        // Allow accepting signals (like SIGINT), without having signals delivered to the watcher impact the cluster
        \posix_setsid();
    }
    
    try {
        // Read random IPC hub URI and associated key from a process channel.
        ['id' => $id, 'groupId' => $groupId, 'uri' => $uri, 'key' => $key,
         'type' => $type, 'entryPoint' => $entryPointClassName, 'groupsScheme' => $groupsScheme]
            = $channel->receive();
        
    } catch (\Throwable $exception) {
        throw new FatalWorkerException('Could not connect to IPC socket', 0, $exception);
    }
    
    if (\function_exists('cli_set_process_title')) {
        \set_error_handler(static fn () => true);
        try {
            \cli_set_process_title($type.' worker #'.$id. ' group #'.$groupId);
        } finally {
            \restore_error_handler();
        }
    }
    
    $strategy                       = null;
    
    try {
        
        if (class_exists($entryPointClassName)) {
            $entryPoint             = new $entryPointClassName();
        } else {
            throw new FatalWorkerException('Entry point class not found: ' . $entryPointClassName);
        }
        
        if (false === $entryPoint instanceof WorkerEntryPointInterface) {
            throw new FatalWorkerException('Entry point class must implement WorkerEntryPointI');
        }
        
        $strategy                   = new Worker((int)$id, (int)$groupId, $channel, $key, $uri, $type, $groupsScheme);
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
        
        if(false === $exception instanceof FatalWorkerException) {
            // Make sure that the exception is a FatalWorkerException
            $exception = new FatalWorkerException('Worker encountered a fatal error', 0, $exception);
        }
        
        $channel->send($exception);
        throw $exception;
    } finally {
        $strategy?->close();
    }
};
