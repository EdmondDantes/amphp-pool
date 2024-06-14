<?php
declare(strict_types=1);

namespace CT\AmpCluster\Worker;

interface WorkerEntryPointInterface
{
    public function initialize(WorkerInterface $workerStrategy): void;
    public function run(): void;
}