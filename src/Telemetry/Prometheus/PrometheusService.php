<?php
declare(strict_types=1);

namespace CT\AmpPool\Telemetry\Prometheus;

use Amp\Http\HttpStatus;
use Amp\Http\Server\DefaultErrorHandler;
use Amp\Http\Server\RequestHandler\ClosureRequestHandler;
use Amp\Http\Server\Response;
use Amp\Http\Server\SocketHttpServer;
use CT\AmpPool\Worker\WorkerEntryPointInterface;
use CT\AmpPool\Worker\WorkerInterface;

class PrometheusService implements WorkerEntryPointInterface
{
    public const string MIME_TYPE = 'text/plain; version=0.0.4';
    
    protected \WeakReference $worker;
    
    protected function getWorker(): WorkerEntryPointInterface
    {
        return $this->worker->get();
    }
    
    public function initialize(WorkerInterface $worker): void
    {
        $this->worker = \WeakReference::create($worker);
    }
    
    public function run(): void
    {
        $worker                     = $this->worker->get();
        
        if ($worker === null) {
            return;
        }

        $prometheusProvider         = new PrometheusProvider($worker->getWorkersStorage());
        $httpServer                 = SocketHttpServer::createForDirectAccess($worker->getLogger());
        
        // 2. Expose the server to the network
        $httpServer->expose('0.0.0.0:9090');
        
        // 3. Handle incoming connections and start the server
        $httpServer->start(
            new ClosureRequestHandler(static function () use ($prometheusProvider): Response {
                
                return new Response(
                    HttpStatus::OK, ['content-type' => self::MIME_TYPE], $prometheusProvider->render()
                );
            }),
            new DefaultErrorHandler(),
        );
        
        $worker->awaitTermination();
        $httpServer->stop();
    }
}