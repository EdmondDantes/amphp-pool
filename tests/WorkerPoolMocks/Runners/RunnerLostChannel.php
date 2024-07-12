<?php
declare(strict_types=1);

namespace IfCastle\AmpPool\WorkerPoolMocks\Runners;

use Amp\Sync\Channel;
use IfCastle\AmpPool\Strategies\RunnerStrategy\DefaultRunner;
use function Amp\delay;

final class RunnerLostChannel extends DefaultRunner
{
    public static function processEntryPoint(Channel $channel): void
    {
        // Break the channel
        $channel->close();

        delay(2);
    }

    public function getScript(): string|array
    {
        return __DIR__ . '/runner.php';
    }
}
