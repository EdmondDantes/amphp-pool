<?php
declare(strict_types=1);

namespace CT\AmpServer\WorkerState;

final readonly class WorkerState
{
    const int SIZE = 4 * 4;
    
    public function __construct(public bool $isReady, public int $jobCount, public int $groupId) {}
    
    public function pack(): string
    {
        return pack('L*', $this->isReady ? 1 : 0, $this->jobCount, $this->groupId);
    }
    
    public static function unpack(string $data): self
    {
        $result = unpack('L*', $data);
        
        if(false === $result || count($result) !== 4) {
            throw new \RuntimeException('Failed to unpack data');
        }
        
        [, $isReady, $jobCount, $groupId] = $result;
        
        return new self((bool) $isReady, $jobCount, $groupId);
    }
}