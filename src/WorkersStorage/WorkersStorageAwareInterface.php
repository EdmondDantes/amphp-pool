<?php
declare(strict_types=1);

namespace CT\AmpPool\WorkersStorage;

interface WorkersStorageAwareInterface
{
    public function getWorkersStorage(): WorkersStorageInterface;
}