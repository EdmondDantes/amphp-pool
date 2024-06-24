<?php
declare(strict_types=1);

namespace CT\AmpPool\JobIpc;

use Amp\DeferredFuture;
use Amp\Future;

interface IpcClientInterface        extends JobClientInterface
{
    public function mainLoop(): void;
    public function close(): void;
}