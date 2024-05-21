<?php
declare(strict_types=1);

namespace CT\AmpServer;

interface WorkerEntryPointI
{
    public function initialize(Worker $workerStrategy): void;
    public function run(): void;
}