<?php
declare(strict_types=1);

namespace CT\AmpPool;

interface GlobalErrorHandlerInterface
{
    public function applyGlobalErrorHandler(): void;
}
