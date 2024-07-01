<?php
declare(strict_types=1);

namespace CT\AmpPool\Worker\Internal;

use Amp\ForbidCloning;
use Amp\ForbidSerialization;
use Amp\Sync\Channel;
use CT\AmpPool\Exceptions\RemoteException;
use CT\AmpPool\Internal\Messages\MessageLog;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;
use Psr\Log\LogLevel;

/**
 * Worker Log Handler.
 * The log handler sends log messages to the worker channel.
 * @internal
 */
final class WorkerLogHandler extends AbstractProcessingHandler
{
    use ForbidCloning;
    use ForbidSerialization;

    /**
     * @param value-of<Level::NAMES>|value-of<Level::VALUES>|Level|LogLevel::* $level
     *
     * @psalm-suppress MismatchingDocblockParamType, PossiblyInvalidArgument, UnresolvableConstant
     */
    public function __construct(
        private readonly Channel $channel,
        int|string|Level $level = LogLevel::DEBUG,
        bool $bubble = false,
    ) {
        parent::__construct($level, $bubble);
    }

    /**
     * @param array|LogRecord $record Array for Monolog v1.x or 2.x and {@see LogRecord} for v3.x.
     */
    protected function write(array|LogRecord $record): void
    {
        // Remove all unserializable data.
        $record = $this->removeUnserializableData($record);

        $this->channel->send(new MessageLog($record['message'] ?? '', $record['level_name'] ?? '', $record['context'] ?? []));
    }

    private function removeUnserializableData(array|LogRecord $record, int $recursion = 0): array|LogRecord
    {
        if($recursion > 10) {
            return [];
        }

        if($record instanceof LogRecord) {
            $record                 = $record->toArray();
        }

        if(\is_array($record)) {
            foreach ($record as $key => $value) {
                if(\is_array($value)) {
                    $record[$key] = $this->removeUnserializableData($value, $recursion + 1);
                }

                if($value instanceof RemoteException) {
                    continue;
                }

                if($value instanceof \Throwable) {
                    $record[$key] = $record[$key]->getMessage().' in '.$record[$key]->getFile().':'.$record[$key]->getLine()
                                    .PHP_EOL.$record[$key]->getTraceAsString();
                }

                if(\is_object($value)) {
                    $record[$key] = 'object::'.\get_class($value);
                }

                if(\is_resource($value)) {
                    $record[$key] = 'resource::' . \get_resource_type($value);
                }

                if(\is_callable($value)) {
                    $record[$key] = 'callable(...)';
                }
            }

            return $record;
        }

        return $record;
    }
}
