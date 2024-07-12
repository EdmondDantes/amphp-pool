<?php
declare(strict_types=1);

namespace IfCastle\AmpPool\Worker;

interface WorkerEntryPointInterface
{
    public function initialize(WorkerInterface $worker): void;
    public function run(): void;
}
