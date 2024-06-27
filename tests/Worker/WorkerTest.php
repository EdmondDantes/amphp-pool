<?php
declare(strict_types=1);

namespace CT\AmpPool\Worker;

use Amp\Sync\Channel;
use Amp\TimeoutCancellation;
use CT\AmpPool\Internal\Messages\MessageShutdown;
use CT\AmpPool\WorkerGroup;
use CT\AmpPool\WorkerTypeEnum;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Revolt\EventLoop;
use function Amp\Sync\createChannelPair;

class WorkerTest                    extends TestCase
{
    private Worker      $worker;
    private Channel     $channelIn;
    private Channel     $channelOut;
    private WorkerGroup $workerGroup;
    
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
            $this->channelIn->send(new MessageShutdown);
        });
        
        $this->worker->awaitTermination(new TimeoutCancellation(5));
        
        $this->assertTrue($this->worker->isStopped());
    }
    
    protected function buildChannel(): void
    {
        [$this->channelIn, $this->channelOut] = createChannelPair();
    }
    
    protected function buildWorkerGroup(): void
    {
        $this->workerGroup          = new WorkerGroup(
            '',
            WorkerTypeEnum::JOB,
            1,
        );
    }
    
    protected function buildWorker(): void
    {
        $this->worker              = new Worker(
            1,
            $this->channelOut,
            $this->workerGroup,
            [$this->workerGroup]
        );
    }
}
