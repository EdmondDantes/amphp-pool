<?php
declare(strict_types=1);

namespace CT\AmpServer;

final class WorkerMessage
{
    public function __construct(
        public readonly WorkerMessageType $type,
        public readonly mixed $data
    ) {}
}