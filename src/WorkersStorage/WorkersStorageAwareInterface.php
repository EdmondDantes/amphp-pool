<?php
declare(strict_types=1);

namespace IfCastle\AmpPool\WorkersStorage;

interface WorkersStorageAwareInterface
{
    public function getWorkersStorage(): WorkersStorageInterface;
}
