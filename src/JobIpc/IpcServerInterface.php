<?php
declare(strict_types=1);

namespace IfCastle\AmpPool\JobIpc;

use Amp\ByteStream\StreamChannel;
use Amp\Cancellation;
use Amp\Pipeline\Queue;
use Amp\Socket\SocketAddress;
use Amp\Sync\Channel;

interface IpcServerInterface
{
    public static function getSocketAddress(int $workerId): SocketAddress;
    public function isClosed(): bool;
    public function close(): void;
    public function onClose(\Closure $onClose): void;
    public function receiveLoop(?Cancellation $cancellation = null): void;
    /**
     * @return Queue<array{0: StreamChannel, 1: mixed}>
     */
    public function getJobQueue(): Queue;
    public function getAddress(): SocketAddress;

    public function sendJobResult(mixed $result, Channel $channel, JobRequest $jobRequest, ?Cancellation $cancellation = null): void;
}
