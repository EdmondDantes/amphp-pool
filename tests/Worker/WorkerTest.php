<?php
declare(strict_types=1);

namespace IfCastle\AmpPool\Worker;

use Amp\DeferredCancellation;
use Amp\Sync\Channel;
use Amp\TimeoutCancellation;
use IfCastle\AmpPool\Internal\Messages\MessageIpcShutdown;
use IfCastle\AmpPool\WorkerGroup;
use IfCastle\AmpPool\WorkersStorage\WorkersStorageMemory;
use IfCastle\AmpPool\WorkerTypeEnum;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Revolt\EventLoop;
use function Amp\Sync\createChannelPair;

class WorkerTest extends TestCase
{
    private Worker      $worker;
    private Channel     $channelIn;
    private Channel     $channelOut;
    private WorkerGroup $workerGroup;
    private DeferredCancellation $cancellation;

    protected function setUp(): void
    {

    }

    protected function tearDown(): void
    {

    }

    public function testStop(): void
    {
        $this->buildChannel();
        $this->buildWorkerGroup();
        $this->buildWorker();

        EventLoop::queue($this->worker->mainLoop(...));
        $this->worker->stop();

        $this->worker->awaitTermination(new TimeoutCancellation(5));

        $this->assertTrue($this->worker->isStopped());
    }

    #[RunInSeparateProcess]
    public function testShutdown(): void
    {
        $this->buildChannel();
        $this->buildWorkerGroup();
        $this->buildWorker();

        EventLoop::queue($this->worker->mainLoop(...));

        EventLoop::queue(function () {
            $this->channelIn->send(new MessageIpcShutdown);
        });

        $this->worker->awaitTermination(new TimeoutCancellation(5));

        $this->assertTrue($this->worker->isStopped());
    }

    protected function buildChannel(): void
    {
        $this->cancellation         = new DeferredCancellation;

        [$this->channelIn, $this->channelOut] = createChannelPair();

        EventLoop::queue(function () {
            $this->channelIn->receive($this->cancellation->getCancellation());
        });
    }

    protected function buildWorkerGroup(): void
    {
        $this->workerGroup          = new WorkerGroup(
            '',
            WorkerTypeEnum::JOB,
            1,
            maxWorkers: 1
        );
    }

    protected function buildWorker(): void
    {
        $this->worker              = new Worker(
            1,
            $this->channelOut,
            $this->workerGroup,
            [$this->workerGroup],
            WorkersStorageMemory::class
        );

        $this->worker->initWorker();
    }
}
