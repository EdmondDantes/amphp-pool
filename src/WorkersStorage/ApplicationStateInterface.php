<?php
declare(strict_types=1);

namespace CT\AmpPool\WorkersStorage;

interface ApplicationStateInterface
{
    public static function instanciate(): static;
    public function getStructureSize(): int;
    
    public function getUptime(): int;
    
    public function getStartedAt(): int;
    
    public function getLastRestartedAt(): int;
    
    public function getRestartsCount(): int;
    
    public function getWorkersErrors(): int;
}