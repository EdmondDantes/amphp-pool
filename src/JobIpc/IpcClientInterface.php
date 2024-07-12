<?php
declare(strict_types=1);

namespace IfCastle\AmpPool\JobIpc;

interface IpcClientInterface extends JobClientInterface
{
    public function mainLoop(): void;
    public function close(): void;
}
