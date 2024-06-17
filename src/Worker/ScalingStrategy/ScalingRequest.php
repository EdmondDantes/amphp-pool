<?php
declare(strict_types=1);

namespace CT\AmpPool\Worker\ScalingStrategy;

final readonly class ScalingRequest
{
    public function __construct(public int $toGroupId) {}
}