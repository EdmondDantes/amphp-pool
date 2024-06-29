<?php
declare(strict_types=1);

namespace CT\AmpPool;

interface WorkerEventEmitterAwareInterface
{
    public function getWorkerEventEmitter(): WorkerEventEmitterInterface;
}
