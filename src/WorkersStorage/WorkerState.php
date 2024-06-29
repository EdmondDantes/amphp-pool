<?php
declare(strict_types=1);

namespace CT\AmpPool\WorkersStorage;

class WorkerState                    implements WorkerStateInterface
{
    public static function instanciateFromStorage(
        WorkersStorageInterface $storage,
        int $workerId
    ): WorkerStateInterface
    {
        $workerState                = new static($workerId);
        $workerState->storage       = \WeakReference::create($storage);
        
        return $workerState;
    }
    
    private \WeakReference|null $storage    = null;
    
    public function __construct(
        public readonly int  $workerId      = 0,
        public int  $groupId                = 0,
        
        public bool $shouldBeStarted         = false,
        public bool $isReady                 = false,
        public int $totalReloaded            = 0,
        /**
         * Current worker weight.
         */
        public int  $weight                  = 0,
        
        public int  $firstStartedAt          = 0,
        public int  $startedAt               = 0,
        public int  $finishedAt              = 0,
        public int  $updatedAt               = 0,
        
        public int  $phpMemoryUsage          = 0,
        public int  $phpMemoryPeakUsage      = 0,
        
        public int  $connectionsAccepted     = 0,
        public int  $connectionsProcessed    = 0,
        public int  $connectionsErrors       = 0,
        public int  $connectionsRejected     = 0,
        public int  $connectionsProcessing   = 0,
        
        public int  $jobAccepted             = 0,
        public int  $jobProcessed            = 0,
        public int  $jobProcessing           = 0,
        public int  $jobErrors               = 0,
        public int  $jobRejected             = 0
    ) {}
    
    protected function getStorage(): WorkersStorageInterface
    {
        return $this->storage->get();
    }
    
    public static function getItemSize(): int
    {
        // 1. Each integer value is 8 bytes.
        // 2. Each boolean value is 8 bytes.
        return 32 * 8;
    }
    
    public function read(): static
    {
        $data                       = $this->getStorage()->readWorkerState($this->workerId);
        $data                       = unpack('Q*', $data);
        
        if(false === $data) {
            throw new \RuntimeException('Failed to read worker state');
        }
        
        $data[3]                    = (bool)$data[3];
        $data[4]                    = (bool)$data[4];
        
        [,
            $this->groupId,
            
            $this->shouldBeStarted,
            $this->isReady,
            $this->weight,
            $this->totalReloaded,
         
            $this->firstStartedAt,
            $this->startedAt,
            $this->finishedAt,
            $this->updatedAt,
            
            $this->phpMemoryUsage,
            $this->phpMemoryPeakUsage,
            
            $this->connectionsAccepted,
            $this->connectionsProcessed,
            $this->connectionsErrors,
            $this->connectionsRejected,
            $this->connectionsProcessing,
            
            $this->jobAccepted,
            $this->jobProcessed,
            $this->jobProcessing,
            $this->jobErrors,
            $this->jobRejected
        ] = array_values($data);
        
        return $this;
    }
    
    public function update(): static
    {
        $this->getStorage()->updateWorkerState($this->workerId, $this->packItem());
        
        return $this;
    }
    
    public function updateStateSegment(): static
    {
        [$data, $offset]            = $this->packStateSegment();
        
        $this->getStorage()->updateWorkerState($this->workerId, $data, $offset);
        
        return $this;
    }
    
    public function updateTimeSegment(): static
    {
        [$data, $offset]            = $this->packTimeSegment();
        
        $this->getStorage()->updateWorkerState($this->workerId, $data, $offset);
        
        return $this;
    }
    
    public function updateMemorySegment(): static
    {
        [$data, $offset]            = $this->packMemorySegment();
        
        $this->getStorage()->updateWorkerState($this->workerId, $data, $offset);
        
        return $this;
    }
    
    public function updateConnectionsSegment(): static
    {
        [$data, $offset]            = $this->packConnectionSegment();
        
        $this->getStorage()->updateWorkerState($this->workerId, $data, $offset);
        
        return $this;
    }
    
    public function updateJobSegment(): static
    {
        [$data, $offset]            = $this->packJobSegment();
        
        $this->getStorage()->updateWorkerState($this->workerId, $data, $offset);
        
        return $this;
    }
    
    protected function packItem(): string
    {
        return pack(
            'Q*',
            $this->workerId,
            $this->groupId,
            
            $this->shouldBeStarted,
            $this->isReady,
            $this->weight,
            $this->totalReloaded,
            
            $this->firstStartedAt,
            $this->startedAt,
            $this->finishedAt,
            $this->updatedAt,
            
            $this->phpMemoryUsage,
            $this->phpMemoryPeakUsage,
            
            $this->connectionsAccepted,
            $this->connectionsProcessed,
            $this->connectionsErrors,
            $this->connectionsRejected,
            $this->connectionsProcessing,
            
            $this->jobAccepted,
            $this->jobProcessed,
            $this->jobProcessing,
            $this->jobErrors,
            $this->jobRejected
        );
    }
    
    protected static function unpackItem(string $packedItem): WorkerStateInterface
    {
        $unpackedItem               = unpack('Q*', $packedItem);
        
        return new WorkerState(
            $unpackedItem[1] ?? 0,
            $unpackedItem[2] ?? 0,
                
                (bool)($unpackedItem[3] ?? false),
                (bool)($unpackedItem[4] ?? false),
            $unpackedItem[5] ?? 0,
            
            $unpackedItem[6] ?? 0,
            $unpackedItem[7] ?? 0,
            $unpackedItem[8] ?? 0,
            $unpackedItem[9] ?? 0,
            
            $unpackedItem[10] ?? 0,
            $unpackedItem[11] ?? 0,
            
            $unpackedItem[12] ?? 0,
            $unpackedItem[13] ?? 0,
            $unpackedItem[14] ?? 0,
            $unpackedItem[15] ?? 0,
            $unpackedItem[16] ?? 0,
            
            $unpackedItem[17] ?? 0,
            $unpackedItem[18] ?? 0,
            $unpackedItem[19] ?? 0,
            $unpackedItem[20] ?? 0,
            $unpackedItem[21] ?? 0
        );
    }
    
    protected function packStateSegment(): array
    {
        return [
            pack(
                'Q*',
                $this->shouldBeStarted,
                $this->isReady,
                $this->weight,
                $this->totalReloaded
            ),
            2 * 8
            ];
    }
    
    protected function packTimeSegment(): array
    {
        return [
            pack(
                'Q*',
                $this->firstStartedAt,
                $this->startedAt,
                $this->finishedAt,
                $this->updatedAt
            ),
            5 * 8
        ];
    }
    
    protected function packMemorySegment(): array
    {
        return [
            pack(
                'Q*',
                $this->phpMemoryUsage,
                $this->phpMemoryPeakUsage
            ),
            9 * 8
        ];
    }
    
    protected function packConnectionSegment(): array
    {
        return [
            pack(
                'Q*',
                $this->connectionsAccepted,
                $this->connectionsProcessed,
                $this->connectionsErrors,
                $this->connectionsRejected,
                $this->connectionsProcessing
            ),
            11 * 8
        ];
    }
    
    protected function packJobSegment(): array
    {
        return [
            pack(
                'Q*',
                $this->jobAccepted,
                $this->jobProcessed,
                $this->jobProcessing,
                $this->jobErrors,
                $this->jobRejected
            ),
            16 * 8
        ];
    }
}