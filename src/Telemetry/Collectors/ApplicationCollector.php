<?php
declare(strict_types=1);

namespace CT\AmpPool\Telemetry\Collectors;

use CT\AmpPool\System\SystemInfo;
use CT\AmpPool\WorkersStorage\ApplicationStateInterface;
use CT\AmpPool\WorkersStorage\MemoryUsageInterface;
use CT\AmpPool\WorkersStorage\WorkersStorageInterface;

final readonly class ApplicationCollector implements ApplicationCollectorInterface
{
    public static function instanciate(WorkersStorageInterface $workersStorage): ApplicationCollectorInterface
    {
        return new self($workersStorage->getApplicationState(), $workersStorage->getMemoryUsage());
    }

    public function __construct(
        private ApplicationStateInterface $applicationState,
        private MemoryUsageInterface      $memoryUsage
    ) {
    }

    public function startApplication(): void
    {
        $stat                       = SystemInfo::systemStat();

        $this->applicationState
            ->setStartedAt(\time())
            ->setRestartsCount(0)
            ->setLastRestartedAt(0)
            ->setWorkersErrors(0)
            ->setLoadAverage($stat['load_average'] ?? 0.0)
            ->setMemoryTotal($stat['memory_total'] ?? 0)
            ->setMemoryFree($stat['memory_free'] ?? 0)
            ->update();
    }

    public function updateApplicationState(array $workersPid): void
    {
        $stat                       = SystemInfo::systemStat();

        $this->applicationState
            ->setLoadAverage($stat['load_average'] ?? 0.0)
            ->setMemoryTotal($stat['memory_total'] ?? 0)
            ->setMemoryFree($stat['memory_free'] ?? 0)
            ->update();

        $memoryUsage                = [];

        foreach ($workersPid as $pid) {
            $memoryUsage[]          = $pid !== 0 ? SystemInfo::getProcessMemoryUsage($pid) : 0;
        }

        $this->memoryUsage->setStats($memoryUsage)->update();
    }

    public function restartApplication(): void
    {
        $this->applicationState->setRestartsCount($this->applicationState->getRestartsCount() + 1)->update();
    }

    public function stopApplication(): void
    {
    }

    public function flushTelemetry(): void
    {
        $this->applicationState->update();
        $this->memoryUsage->update();
    }
}
