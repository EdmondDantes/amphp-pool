<?php
declare(strict_types=1);

namespace IfCastle\AmpPool\Internal\Messages;

/**
 * @internal
 */
final readonly class MessageLog
{
    public function __construct(
        public string $message,
        public string $level        = \Psr\Log\LogLevel::INFO,
        public array  $context      = []
    ) {
    }

}
