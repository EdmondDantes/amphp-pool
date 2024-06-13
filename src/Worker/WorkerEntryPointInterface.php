<?php
declare(strict_types=1);

namespace CT\AmpServer\Worker;

interface WorkerEntryPointInterface
{
    public function initialize(WorkerInterface $workerStrategy): void;
    public function run(): void;
}