<?php
declare(strict_types=1);

namespace IfCastle\AmpPool\Exceptions;

/**
 * TerminateWorkerException is thrown when a worker should be terminated,
 * but the workersPool don't need to restart the worker.
 */
final class TerminateWorkerException extends RemoteException
{

}
