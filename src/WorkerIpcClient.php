<?php
declare(strict_types=1);

namespace CT\AmpServer;

use Amp\Cancellation;
use Amp\ForbidCloning;
use Amp\ForbidSerialization;
use Amp\Socket\Socket;
use Amp\TimeoutCancellation;
use function Amp\Socket\socketConnector;

final class WorkerIpcClient
{
    use ForbidCloning;
    use ForbidSerialization;
    
    private array $workerSockets = [];
    
    public function __construct(private int $workerId, private int $workerGroupId = 0, private Cancellation|null $cancellation = null)
    {
        if($this->cancellation === null) {
            $this->cancellation     = new TimeoutCancellation(5);
        }
    }
    
    public function sendJob(mixed $data): void
    {
    
    }
    
    public function receiveResult(): mixed
    {
    
    }
    
    public function __destruct()
    {
    }
    
    private function selectWorker(): int
    {
    
    }
    
    private function getWorkerSocket(int $workerId): Socket
    {
        if(array_key_exists($workerId, $this->workerSockets)) {
            return $this->workerSockets[$workerId];
        }
        
        $this->workerSockets[$workerId] = $this->createConnectToWorker($workerId);
        
        return $this->workerSockets[$workerId];
    }
    
    private function createConnectToWorker(int $workerId): Socket
    {
        $connector                  = socketConnector();
        
        $client                     = $connector->connect(
            WorkerIpcServer::getSocketAddress($workerId), cancellation: $this->cancellation
        );
        
        $client->write(WorkerIpcServer::HAND_SHAKE);
        
        return $client;
    }
}