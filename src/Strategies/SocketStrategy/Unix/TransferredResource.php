<?php
declare(strict_types=1);

namespace IfCastle\AmpPool\Strategies\SocketStrategy\Unix;

use Amp\ForbidCloning;
use Amp\ForbidSerialization;

/**
 * @template-covariant T
 */
final class TransferredResource
{
    use ForbidCloning;
    use ForbidSerialization;

    /**
     * @param resource $resource Stream-socket resource.
     * @param T $data
     */
    public function __construct(
        private readonly mixed $resource,
        private readonly mixed $data,
    ) {
    }

    /**
     * @return resource Stream-socket resource.
     */
    public function getResource(): mixed
    {
        return $this->resource;
    }

    /**
     * @return T
     */
    public function getData(): mixed
    {
        return $this->data;
    }
}
