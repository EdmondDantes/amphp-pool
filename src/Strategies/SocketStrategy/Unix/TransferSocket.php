<?php
declare(strict_types=1);

namespace IfCastle\AmpPool\Strategies\SocketStrategy\Unix;

use Amp\ByteStream\ResourceStream;
use Amp\Closable;
use Amp\DeferredFuture;
use Amp\ForbidCloning;
use Amp\ForbidSerialization;
use Amp\Socket\SocketException;
use Socket as ExtSocketResource;

/** @internal */
final class TransferSocket implements Closable
{
    use ForbidCloning;
    use ForbidSerialization;

    private readonly ExtSocketResource $socket;

    private readonly \Closure $errorHandler;

    private readonly DeferredFuture $onClose;

    public function __construct(ResourceStream $socket)
    {
        if (!\extension_loaded('sockets')) {
            throw new \Error('ext-sockets is required for ' . self::class);
        }

        $streamResource = $socket->getResource();
        if (!\is_resource($streamResource)) {
            throw new SocketException('The provided socket has already been closed');
        }

        $socketResource = \socket_import_stream($streamResource);
        if (!$socketResource instanceof ExtSocketResource) {
            throw new SocketException('Unable to import transfer socket from stream socket resource');
        }

        $this->socket = $socketResource;
        $this->errorHandler = $errorHandler = static fn () => true;
        $this->onClose = new DeferredFuture();

        $this->onClose(static function () use ($socketResource, $errorHandler): void {
            \set_error_handler($errorHandler);
            try {
                \socket_close($socketResource);
            } finally {
                \restore_error_handler();
            }
        });
    }

    public function __destruct()
    {
        $this->close();
    }

    public function close(): void
    {
        if (!$this->onClose->isComplete()) {
            $this->onClose->complete();
        }
    }

    public function isClosed(): bool
    {
        return $this->onClose->isComplete();
    }

    public function onClose(\Closure $onClose): void
    {
        $this->onClose->getFuture()->finally($onClose);
    }

    /**
     * @return TransferredResource<string>|null
     *
     * @throws SocketException
     *
     * @psalm-suppress InvalidArrayOffset $data array is overwritten by socket_recvmsg().
     */
    public function receiveSocket(): ?TransferredResource
    {
        $data = ['controllen' => \socket_cmsg_space(\SOL_SOCKET, \SCM_RIGHTS) + 4];

        // Error checked manually if socket_sendmsg() fails.
        \set_error_handler($this->errorHandler);
        \socket_clear_error();

        try {
            if (!\socket_recvmsg($this->socket, $data, \MSG_DONTWAIT)) {
                /* Purposely omitting $this->socket from socket_last_error(),
                 * as the error will not be socket-specific. */
                $errorCode = \socket_last_error();
                if ($errorCode === \SOCKET_EAGAIN) {
                    return null;
                }

                throw new SocketException(\sprintf(
                    'Could not transfer socket: (%d) %s',
                    $errorCode,
                    \socket_strerror($errorCode),
                ));
            }
        } finally {
            \restore_error_handler();
        }

        $transferredData = $data['iov'][0];
        $transferredSocket = $data['control'][0]['data'][0];

        \assert(\is_string($transferredData) && $transferredSocket instanceof ExtSocketResource);

        $transferredStream = \socket_export_stream($transferredSocket);
        if (!$transferredStream) {
            throw new SocketException('Failed to import socket to a stream socket resource');
        }

        return new TransferredResource($transferredStream, $transferredData);
    }

    /**
     * @param resource $stream Stream socket resource.
     *
     * @return bool True if the socket was successfully transferred, false if the pipe was full and will
     *  need to be retried.
     *
     * @throws SocketException
     */
    public function sendSocket($stream, string $data): bool
    {
        /** @psalm-suppress DocblockTypeContradiction */
        if (!\is_resource($stream)) {
            throw new SocketException('The stream resource closed before being transferred');
        }

        // Error checked manually if socket_sendmsg() fails.
        \set_error_handler($this->errorHandler);
        \socket_clear_error($this->socket);

        try {
            if (!\socket_sendmsg($this->socket, [
                'iov' => [$data],
                'control' => [
                    ['level' => \SOL_SOCKET, 'type' => \SCM_RIGHTS, 'data' => [$stream]],
                ],
            ], \MSG_DONTWAIT)) {
                $errorCode = \socket_last_error($this->socket);
                if ($errorCode === \SOCKET_EAGAIN) {
                    // Socket buffer full, try again later.
                    return false;
                }

                throw new SocketException(\sprintf(
                    'Could not transfer socket: (%d) %s',
                    $errorCode,
                    \socket_strerror($errorCode),
                ));
            }

            return true;
        } finally {
            \restore_error_handler();
        }
    }
}
