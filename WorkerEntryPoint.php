<?php
declare(strict_types=1);

namespace CT\AmpServer;

use Amp\Http\HttpStatus;
use Amp\Http\Server\DefaultErrorHandler;
use Amp\Http\Server\Driver\SocketClientFactory;
use Amp\Http\Server\RequestHandler\ClosureRequestHandler;
use Amp\Http\Server\Response;
use Amp\Http\Server\SocketHttpServer;
use Amp\Socket\BindContext;
use Amp\Socket\ResourceServerSocketFactory;

final class WorkerEntryPoint implements WorkerEntryPointI
{
    private WorkerRunner $workerStrategy;
    
    public function initialize(WorkerRunner $workerStrategy): void
    {
        $this->workerStrategy = $workerStrategy;
    }
    
    public function run(): void
    {
        file_put_contents(__DIR__.'/worker.log', "Worker {$this->workerStrategy} runner" . PHP_EOL, FILE_APPEND);

        // Cluster::getServerSocketFactory() will return a factory which creates the socket
        // locally or requests the server socket from the cluster watcher.
        $socketFactory              = $this->workerStrategy->getSocketPipeFactory();
        //$socketFactory              = new ResourceServerSocketFactory();
        $clientFactory              = new SocketClientFactory($this->workerStrategy->getLogger());
        
        $httpServer                 = new SocketHttpServer($this->workerStrategy->getLogger(), $socketFactory, $clientFactory);
        $httpServer->expose('127.0.0.1:9095');

        // Start the HTTP server
        $httpServer->start(
            new ClosureRequestHandler(function (): Response {
                
                //sleep(10);
                
                return new Response(HttpStatus::OK, [
                    "content-type" => "text/plain; charset=utf-8",
                ], "Hello, World! ".$this->workerStrategy->getWorkerId());
            }),
            new DefaultErrorHandler(),
        );
        
        $this->workerStrategy->awaitTermination();
        
        $httpServer->stop();
    }
}