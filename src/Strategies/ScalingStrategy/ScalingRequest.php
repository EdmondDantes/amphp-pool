<?php
declare(strict_types=1);

namespace IfCastle\AmpPool\Strategies\ScalingStrategy;

final readonly class ScalingRequest
{
    public function __construct(public int $toGroupId)
    {
    }
}
