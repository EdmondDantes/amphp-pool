<?php
declare(strict_types=1);

namespace CT\AmpPool\EntryPoints;

use CT\AmpPool\Worker\WorkerEntryPointInterface;
use CT\AmpPool\Worker\WorkerInterface;

final class TestEntryPoint implements WorkerEntryPointInterface
{
    public static function getFile(): string
    {
        return sys_get_temp_dir() . '/worker-pool-test.text';
    }
    
    public static function removeFile(): void
    {
        $file                       = self::getFile();
        
        if(file_exists($file)) {
            unlink($file);
        }
        
        if(file_exists($file)) {
            throw new \RuntimeException('Could not remove file: ' . $file);
        }
    }
    
    public function initialize(WorkerInterface $worker): void
    {
    }
    
    public function run(): void
    {
        file_put_contents(self::getFile(), 'Hello, World!');
    }
}