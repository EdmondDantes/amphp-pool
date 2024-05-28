<?php
declare(strict_types=1);

namespace CT\AmpServer\Messages;

final readonly class MessageJobResult
{
    public function __construct(public mixed $result) {}
}