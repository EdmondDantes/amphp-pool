<?php
declare(strict_types=1);

namespace IfCastle\AmpPool\Internal\Messages;

/**
 * Used to notify the Worker that it can finish its work at its convenience.
 * The parent process will not attempt to stop it forcibly.
 * This is useful for the scaling algorithm when it is necessary to indicate
 * to the Worker to stop working after a successful task.
 */
final class WorkerSoftShutdown
{

}
