<?php
declare(strict_types=1);

namespace IfCastle\AmpPool\Strategies\SocketStrategy;

use Amp\Socket\ServerSocketFactory;

interface SocketStrategyInterface
{
    /**
     * Returns the server socket factory.
     *
     * This method can be used only inside the worker!
     */
    public function getServerSocketFactory(): ServerSocketFactory|null;
}
