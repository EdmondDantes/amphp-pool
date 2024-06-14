<?php
declare(strict_types=1);

namespace CT\AmpCluster\Messages;

final readonly class MessageJob
{
    public function __construct(public mixed $data) {}
}