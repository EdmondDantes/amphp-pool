<?php
declare(strict_types=1);

namespace IfCastle\AmpPool\Strategies\SocketStrategy;

use Amp\Parallel\Context\DefaultContextFactory;
use Amp\TimeoutCancellation;
use IfCastle\AmpPool\Strategies\RestartStrategy\RestartNever;
use IfCastle\AmpPool\WorkerGroup;
use IfCastle\AmpPool\WorkerPool;
use IfCastle\AmpPool\WorkerTypeEnum;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Revolt\EventLoop;

class SocketStrategyTest extends TestCase
{
    public function testStrategy(): void
    {
        TestHttpReactor::removeFile();

        $logger = new Logger('test');
        $logger->pushHandler(new StreamHandler('php://stdout', Level::Warning));
        $logger->useLoggingLoopDetection(false);

        $workerPool                 = new WorkerPool();

        $workerPool->describeGroup(new WorkerGroup(
            TestHttpReactor::class,
            WorkerTypeEnum::REACTOR,
            minWorkers:      1,
            restartStrategy: new RestartNever
        ));

        EventLoop::delay(1, function () {

            $contextFactory         = new DefaultContextFactory();
            $context                = $contextFactory->start(__DIR__ . '/connectionTester.php', new TimeoutCancellation(5));

            $context->send('http://'.TestHttpReactor::ADDRESS.'/');
            $response               = $context->receive(new TimeoutCancellation(5));

            $this->assertEquals(TestHttpReactor::class, $response);
        });

        $workerPool->run();

        $this->assertFileExists(TestHttpReactor::getFile());

        TestHttpReactor::removeFile();
    }
}
