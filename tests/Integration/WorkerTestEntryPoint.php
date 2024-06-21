<?php
declare(strict_types=1);

namespace CT\AmpPool\Integration;

use Amp\DeferredCancellation;
use CT\AmpPool\Exceptions\FatalWorkerException;
use CT\AmpPool\JobIpc\IpcClient;
use CT\AmpPool\JobIpc\IpcServer;
use CT\AmpPool\Worker\WorkerEntryPointInterface;
use CT\AmpPool\Worker\WorkerInterface;
use CT\AmpPool\WorkerTypeEnum;
use Revolt\EventLoop;

final class WorkerTestEntryPoint    implements WorkerEntryPointInterface
{
    private const string JOB_TEST    = 'JOB_TEST';
    public const string RESULT_FILE  = '/amp_worker_test_result.txt';
    
    private WorkerInterface $workerStrategy;
    
    #[\Override] public function initialize(WorkerInterface $worker): void
    {
        $this->workerStrategy       = $worker;
    }
    
    #[\Override] public function run(): void
    {
        if($this->workerStrategy->getWorkerType() === WorkerTypeEnum::REACTOR->value) {
            
            $deferredCancellation   = new DeferredCancellation();
            
            $jobIpcClient           = new IpcClient(
                $this->workerStrategy->getWorkerId(),
                $this->workerStrategy->getWorkerGroupId(),
                null,
                $deferredCancellation->getCancellation()
            );
            
            EventLoop::queue($jobIpcClient->mainLoop(...));
            
            /*
            EventLoop::delay(5, static function () use ($deferredCancellation): void {
                $deferredCancellation->cancel(new FatalWorkerException('Timeout for Reactor Worker'));
            });
            */
            
            try {
                $resultFuture           = $jobIpcClient->sendJob(
                    self::JOB_TEST, $this->workerStrategy->getWorkerGroupId() + 1, true
                );
                
                $result                 = $resultFuture->await($deferredCancellation->getCancellation());
            } catch (\Throwable $exception) {
                $deferredCancellation->cancel($exception);
                throw new FatalWorkerException('Reactor Worker failed', 0, $exception);
            }
            
            if($result instanceof \Throwable) {
                $deferredCancellation->cancel($result);
                throw new FatalWorkerException('Reactor Worker failed', 0, $result);
            }
            
            $tmpFile                = sys_get_temp_dir() . self::RESULT_FILE;
            file_put_contents($tmpFile, $result);
            
            $deferredCancellation->cancel(new \Exception('Reactor Worker is done'));
        
        } elseif ($this->workerStrategy->getWorkerType() === WorkerTypeEnum::JOB->value) {
            
            // Code for Job Worker
            $jobIpcServer           = new IpcServer($this->workerStrategy->getWorkerId());
            $deferredCancellation   = new DeferredCancellation();
            
            EventLoop::queue($jobIpcServer->receiveLoop(...), $deferredCancellation->getCancellation());
            
            EventLoop::delay(5000, static function () use ($deferredCancellation): void {
                $deferredCancellation->cancel(new FatalWorkerException('Timeout for Job Worker'));
            });
            
            EventLoop::queue(static function () use ($jobIpcServer, $deferredCancellation): void {
                $iterator           = $jobIpcServer->getJobQueue()->iterate();
                $abortCancellation  = $deferredCancellation->getCancellation();
                
                while($iterator->continue($abortCancellation)) {
                    [$channel, $data]            = $iterator->getValue();
                    
                    if($data === self::JOB_TEST) {
                        $channel->send('OK');
                    } else {
                        $channel->send('ERROR');
                    }
                    
                    $deferredCancellation->cancel(new \Exception('Job Worker is done'));
                }
            });
        }
        
        $this->workerStrategy->awaitTermination();
    }
}