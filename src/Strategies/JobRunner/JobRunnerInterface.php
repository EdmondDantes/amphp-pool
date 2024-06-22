<?php
declare(strict_types=1);

namespace CT\AmpPool\Strategies\JobRunner;

use Amp\Cancellation;
use Amp\Future;

interface JobRunnerInterface
{
    /**
     * @param string    $data
     * @param int|null  $priority
     *
     * @return  Future
     */
    public function runJob(string $data, int $priority = null, Cancellation $cancellation = null): Future;
    public function getJobCount(): int;
    public function awaitAll(): void;
    public function stopAll(\Throwable $throwable = null): bool;
}