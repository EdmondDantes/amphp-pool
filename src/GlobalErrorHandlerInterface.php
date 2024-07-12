<?php
declare(strict_types=1);

namespace IfCastle\AmpPool;

interface GlobalErrorHandlerInterface
{
    public function applyGlobalErrorHandler(): void;
}
