<?php
declare(strict_types=1);

namespace Examples\PidFile;

use Amp\Http\HttpStatus;
use Amp\Http\Server\DefaultErrorHandler;
use Amp\Http\Server\Driver\SocketClientFactory;
use Amp\Http\Server\RequestHandler\ClosureRequestHandler;
use Amp\Http\Server\Response;
use Amp\Http\Server\SocketHttpServer;
use IfCastle\AmpPool\Worker\WorkerEntryPointInterface;
use IfCastle\AmpPool\Worker\WorkerInterface;

/**
 * This class is the entry point of the reactor process,
 * which is designed to handle incoming connections.
 *
 * @package Examples\HttpServer
 */
final class HttpReactor implements WorkerEntryPointInterface
{
    private ?\WeakReference $worker = null;

    public function initialize(WorkerInterface $worker): void
    {
        // 1. This method receives a class that handles the abstraction of the Worker process.
        // The method is called before the run() method.
        $this->worker               = \WeakReference::create($worker);
    }

    public function run(): void
    {
        // The method is called after the initialize() method.

        // 1. Create a socket server (please see amp/http-server package for more details)

        // The workerStrategy provides the socket factory, which is used to create the server.
        // This is necessary because the socket is initially created in the parent process
        // and only then passed to the child process.

        $worker                     = $this->worker->get();

        if ($worker === null) {
            return;
        }

        $socketFactory              = $worker->getWorkerGroup()->getSocketStrategy()->getServerSocketFactory();
        $clientFactory              = new SocketClientFactory($worker->getLogger());
        $httpServer                 = new SocketHttpServer($worker->getLogger(), $socketFactory, $clientFactory);

        // 2. Expose the server to the network
        $httpServer->expose('127.0.0.1:9095');

        // 3. Handle incoming connections and start the server
        $httpServer->start(
            new ClosureRequestHandler(static function () use ($worker): Response {

                return new Response(
                    HttpStatus::OK,
                    [
                    'content-type' => 'text/plain; charset=utf-8',
                ],
                    'Hello, World! From worker id: '.$worker->getWorkerId()
                   .' and group id: '.$worker->getWorkerGroupId()
                );
            }),
            new DefaultErrorHandler(),
        );

        // 4. Await termination of the worker
        $worker->awaitTermination();

        // 5. Stop the HTTP server
        $httpServer->stop();
    }
}
