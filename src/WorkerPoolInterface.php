<?php
declare(strict_types=1);

namespace CT\AmpServer;

interface WorkerPoolInterface
{
    public function getWorkers(): array;
}