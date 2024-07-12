<?php
declare(strict_types=1);

namespace IfCastle\AmpPool\Strategies\SocketStrategy\Windows;

use Amp\Serialization\SerializationException;
use Amp\Socket\BindContext;
use Amp\Socket\ServerSocket;
use Amp\Socket\ServerSocketFactory;
use Amp\Socket\SocketAddress;
use Amp\Socket\SocketException;
use Amp\Sync\Channel;
use Amp\Sync\ChannelException;
use IfCastle\AmpPool\Strategies\SocketStrategy\Windows\Messages\MessageSocketListen;
use IfCastle\AmpPool\Worker\WorkerInterface;

final readonly class SocketPipeFactoryWindows implements ServerSocketFactory
{
    private \WeakReference $worker;

    public function __construct(private Channel $channel, WorkerInterface $worker)
    {
        $this->worker                = \WeakReference::create($worker);
    }

    public function listen(SocketAddress|string $address, ?BindContext $bindContext = null): ServerSocket
    {
        $bindContext                ??= new BindContext();

        if (false === $address instanceof SocketAddress) {
            // Normalize to SocketAddress here to avoid throwing exception for invalid strings at a receiving end.
            $address                = SocketAddress\fromString($address);
        }

        try {
            $this->channel->send(new MessageSocketListen($address));
        } catch (ChannelException|SerializationException $exception) {
            throw new SocketException(
                'Failed sending request to bind server socket: ' . $exception->getMessage(),
                previous: $exception,
            );
        }

        return new ServerSocketFactoryWindows(
            $this->channel,
            $address,
            $bindContext,
            $this->worker->get()?->getWorkerEventEmitter(),
            $this->worker->get()?->getAbortCancellation()
        );
    }
}
