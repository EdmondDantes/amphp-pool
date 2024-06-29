<?php
declare(strict_types=1);

namespace CT\AmpPool\WorkersStorage;

interface WorkerStateInterface
{
    public static function instanciateFromStorage(WorkersStorageInterface $storage, int $workerId, bool $reviewOnly = false): WorkerStateInterface;
    
    public static function unpackItem(string $packedItem): WorkerStateInterface;
    
    /**
     * Returns the size of the item.
     */
    public static function getItemSize(): int;
    
    public function read(): static;
    public function update(): static;
    
    public function updateStateSegment(): static;
    public function updateTimeSegment(): static;
    
    public function updateMemorySegment(): static;
    
    public function updateConnectionsSegment(): static;
    
    public function updateJobSegment(): static;
}
