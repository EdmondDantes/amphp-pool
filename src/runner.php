<?php
declare(strict_types=1);

use Amp\Future;
use Amp\Sync\Channel;
use function Amp\async;

return static function (Channel $channel): void
{
    if (\function_exists('posix_setsid')) {
        // Allow accepting signals (like SIGINT), without having signals delivered to the watcher impact the cluster
        \posix_setsid();
    }
    
    try {
        // Read random IPC hub URI and associated key from a process channel.
        ['id' => $id, 'uri' => $uri, 'key' => $key, 'type' => $type, 'entryPoint' => $entryPointClassName]
            = $channel->receive();
        
    } catch (\Throwable $exception) {
        throw new \RuntimeException('Could not connect to IPC socket', 0, $exception);
    }
    
    if (\function_exists('cli_set_process_title')) {
        \set_error_handler(static fn () => true);
        try {
            \cli_set_process_title($type.' worker #'.$id);
        } finally {
            \restore_error_handler();
        }
    }
    
    try {
        
        if (class_exists($entryPointClassName)) {
            $entryPoint             = new $entryPointClassName();
        } else {
            throw new \RuntimeException('Entry point class not found: ' . $entryPointClassName);
        }
        
        if (false === $entryPoint instanceof \CT\AmpServer\WorkerEntryPointI) {
            throw new \RuntimeException('Entry point class must implement WorkerEntryPointI');
        }
        
        $strategy                   = new \CT\AmpServer\Worker((int)$id, $channel, $key, $uri, $type);
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
    } catch (\Throwable $exception) {
        file_put_contents(__DIR__.'/test.log',
                          $exception->getMessage().'::'.$exception->getFile().':'.$exception->getLine().PHP_EOL, FILE_APPEND
        );
    } finally {
        $channel->send(null);
    }
};
