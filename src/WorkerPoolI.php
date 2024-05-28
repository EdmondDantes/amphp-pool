<?php
declare(strict_types=1);

namespace CT\AmpServer;

interface WorkerPoolI
{
    public function getWorkers(): array;
}