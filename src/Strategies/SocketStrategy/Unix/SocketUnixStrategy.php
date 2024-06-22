<?php
declare(strict_types=1);

namespace CT\AmpPool\Strategies\SocketStrategy\Unix;

use Amp\Cluster\ServerSocketPipeFactory;
use Amp\Socket\ResourceSocket;
use Amp\Socket\ServerSocketFactory;
use Amp\TimeoutCancellation;
use CT\AmpPool\Strategies\SocketStrategy\SocketStrategyInterface;
use CT\AmpPool\Strategies\WorkerStrategyAbstract;
use Amp\Parallel\Ipc;

final class SocketUnixStrategy      extends WorkerStrategyAbstract
                                    implements SocketStrategyInterface
{
    private ServerSocketPipeFactory|null $socketPipeFactory = null;
    private string $uri = '';
    private string $key = '';
    
    public function __construct(private readonly int $ipcTimeout = 5) {}
    
    public function getServerSocketFactory(): ServerSocketFactory
    {
        if($this->socketPipeFactory !== null) {
            return $this->socketPipeFactory;
        }
        
        $worker                     = $this->getWorker();
        
        if($worker === null) {
            throw new \Error('Wrong usage of the method getServerSocketFactory(). This method can be used only inside the worker!');
        }
        
        $this->socketPipeFactory    = new ServerSocketPipeFactory($this->getIpcForTransferSocket());
        
        return $this->socketPipeFactory;
    }
    
    private function getIpcForTransferSocket(): ResourceSocket
    {
        try {
            $socket                 = Ipc\connect($this->uri, $this->key, new TimeoutCancellation($this->ipcTimeout));
            
            if($socket instanceof ResourceSocket) {
                return $socket;
            } else {
                throw new \RuntimeException('Type of socket is not ResourceSocket');
            }
            
        } catch (\Throwable $exception) {
            throw new \RuntimeException('Could not connect to IPC socket', 0, $exception);
        }
    }
}