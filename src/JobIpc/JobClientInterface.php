<?php
declare(strict_types=1);

namespace IfCastle\AmpPool\JobIpc;

use Amp\DeferredFuture;
use Amp\Future;

interface JobClientInterface
{
    /**
     * Send a job to the worker asynchronously in the separate fiber.
     * If $awaitResult equals True than method returns a Future that will be completed when the job is done.
     *
     * However, the duration of a Job should not exceed the JobTimeout. Therefore, if you want to perform very long tasks,
     * you should consider how to properly organize work between workers or increase the JobTimeout.
     *
     * Please note that if the Job-Worker process terminates unexpectedly, all Futures will be completed with an error.
     *
     * Every time the job should be sent maxTryCount times with a retryInterval between attempts.
     * If retryInterval equals 0, the method will throw an exception if it cannot send the job.
     *
     *
     */
    public function sendJob(
        string $data,
        array  $allowedGroups = [],
        array  $allowedWorkers = [],
        bool   $awaitResult = false,
        int    $priority = 0,
        int    $weight = 0
    ): Future|null;

    /**
     * Try to send a job to the worker immediately in the current fiber.
     *
     *
     * @throws \Throwable
     */
    public function sendJobImmediately(
        string              $data,
        array               $allowedGroups = [],
        array               $allowedWorkers = [],
        bool|DeferredFuture $awaitResult = false,
        int                 $priority = 0,
        int                 $weight = 0
    ): Future|null;
}
