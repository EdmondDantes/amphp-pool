<?php
declare(strict_types=1);

namespace CT\AmpServer;

interface WorkerEntryPointI
{
    public function initialize(WorkerRunner $workerStrategy): void;
    public function run(): void;
}