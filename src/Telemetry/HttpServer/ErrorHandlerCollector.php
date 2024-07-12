<?php
declare(strict_types=1);

namespace IfCastle\AmpPool\Telemetry\HttpServer;

use Amp\Http\Server\ErrorHandler;
use Amp\Http\Server\Request;
use Amp\Http\Server\Response;
use IfCastle\AmpPool\Telemetry\Collectors\ConnectionCollectorInterface;

final readonly class ErrorHandlerCollector implements ErrorHandler
{
    public function __construct(private ErrorHandler $errorHandler, private ConnectionCollectorInterface $collector)
    {
    }

    public function handleError(int $status, ?string $reason = null, ?Request $request = null): Response
    {
        $this->collector->connectionAccepted();
        $this->collector->connectionError();

        return $this->errorHandler->handleError($status, $reason, $request);
    }
}
