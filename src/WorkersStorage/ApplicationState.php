<?php
declare(strict_types=1);

namespace CT\AmpPool\WorkersStorage;

class ApplicationState implements ApplicationStateInterface
{
    public static function instanciate(): static
    {
        // TODO: Implement instanciate() method.
    }
    
    public function getStructureSize(): int
    {
        // TODO: Implement getStructureSize() method.
    }
    
    public function getUptime(): int
    {
        // TODO: Implement getUptime() method.
    }
    
    public function getStartedAt(): int
    {
        // TODO: Implement getStartedAt() method.
    }
    
    public function getLastRestartedAt(): int
    {
        // TODO: Implement getLastRestartedAt() method.
    }
    
    public function getRestartsCount(): int
    {
        // TODO: Implement getRestartsCount() method.
    }
    
    public function getWorkersErrors(): int
    {
        // TODO: Implement getWorkersErrors() method.
    }
}