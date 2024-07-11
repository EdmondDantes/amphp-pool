<?php
declare(strict_types=1);

namespace Examples\HttpServerWithJobs;

use Amp\Cancellation;
use CT\AmpPool\Coroutine\CoroutineInterface;
use CT\AmpPool\Strategies\JobExecutor\JobHandlerInterface;
use CT\AmpPool\Worker\WorkerEntryPointInterface;
use CT\AmpPool\Worker\WorkerInterface;

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
