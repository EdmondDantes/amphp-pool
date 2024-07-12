<?php
declare(strict_types=1);

namespace Examples\HttpServerWithJobs;

use Amp\Cancellation;
use IfCastle\AmpPool\Coroutine\CoroutineInterface;
use IfCastle\AmpPool\Strategies\JobExecutor\JobHandlerInterface;
use IfCastle\AmpPool\Worker\WorkerEntryPointInterface;
use IfCastle\AmpPool\Worker\WorkerInterface;

final class JobWorker implements WorkerEntryPointInterface, JobHandlerInterface
{
    private WorkerInterface $worker;

    public function initialize(WorkerInterface $worker): void
    {
        $this->worker               = $worker;
        $worker->getWorkerGroup()->getJobExecutor()->defineJobHandler($this);
    }

    public function run(): void
    {
        $this->worker->awaitTermination();
    }

    public function handleJob(
        string              $data,
        ?CoroutineInterface $coroutine = null,
        ?Cancellation       $cancellation = null
    ): mixed {
        return "Hello a job: $data\n";
    }
}
