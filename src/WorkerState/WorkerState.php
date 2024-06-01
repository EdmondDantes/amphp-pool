<?php
declare(strict_types=1);

namespace CT\AmpServer\WorkerState;

final readonly class WorkerState
{
    const int SIZE = 8 * 8;
    
    public function __construct(public bool $isReady, public int $jobCount, public int $groupId) {}
    
    public function pack(): string
    {
        return pack('Q*', $this->isReady ? 1 : 0, $this->jobCount, $this->groupId);
    }
    
    public static function unpack(string $data): self
    {
        [$isReady, $jobCount, $groupId] = unpack('Q*', $data);
        
        return new self((bool) $isReady, $jobCount, $groupId);
    }
}