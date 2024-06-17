<?php
declare(strict_types=1);

namespace CT\AmpPool\Messages;

final readonly class MessageScalingRequest
{
    public function __construct(public int $toGroupId, public mixed $exData = null) {}
}