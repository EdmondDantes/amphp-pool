<?php
declare(strict_types=1);

namespace CT\AmpPool\Telemetry\HttpServer;

use Amp\Http\Server\Request;
use Amp\Http\Server\Response;
use CT\AmpPool\WorkersStorage\WorkersStorageInterface;

final class RequestHandler
{
    public function __construct(private readonly WorkersStorageInterface $workersStorage, private readonly \Closure $closure)
    {
    }
    
    public function handleRequest(Request $request): Response
    {
        $response                   = null;
        
        try {
            $response               = ($this->closure)($request);
        } catch (\Throwable $exception) {
        
        } finally {
        
        }
        
        return ($this->closure)($request);
    }
}