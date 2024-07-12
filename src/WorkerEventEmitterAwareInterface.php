<?php
declare(strict_types=1);

namespace IfCastle\AmpPool;

interface WorkerEventEmitterAwareInterface
{
    public function getWorkerEventEmitter(): WorkerEventEmitterInterface;
}
