<?php
declare(strict_types=1);

namespace IfCastle\AmpPool\Strategies\JobExecutor;

use Amp\Cancellation;
use Amp\Future;

interface JobExecutorInterface
{
    public function defineJobHandler(JobHandlerInterface $handler): void;

    public function runJob(string $data, ?int $priority = null, ?int $weight = null, ?Cancellation $cancellation = null): Future;

    /**
     * The method should be called immediately after the runJob() method.
     * The method evaluates whether the Worker can process another Job in the queue if there are any.
     * The method returns true if yes, otherwise false.
     *
     */
    public function canAcceptMoreJobs(): bool;
    public function getJobCount(): int;
    public function awaitAll(?Cancellation $cancellation = null): void;
    public function stopAll(?\Throwable $throwable = null): bool;
}
