<?php

namespace App\Services\MRP;

use App\Services\CalendarService;
use App\Services\PlanningRangeService;
use Illuminate\Database\Connection;

class PlanningEngineDataService
{
    public function __construct(
        private readonly ConfigurationService $configurationService,
        private readonly PlanningRangeService $planningRangeService,
        private readonly CalendarService $calendarService,
    ) {}

    public function getData(
        Connection $as400Connection,
        int $productionPlanningId,
    ): array {
        $planningRange = $this->planningRangeService->getPlanningRange();

        $configuration = $this->configurationService->getConfiguration(
            connection: $as400Connection,
            materialPlanningId: $productionPlanningId,
            planningRange: $planningRange,
        );

        $calendar = $this->calendarService->getCalendarFragment(
            date('Y'),
            $planningRange->getStartJulianDay(),
            $planningRange->getEndJulianDay(),
            $configuration->calendar->value,
        );

        return [
            'productionPlanningId' => $productionPlanningId,
            'configuration' => $configuration,
            'calendar' => $calendar,
            'planningRange' => $planningRange,
        ];
    }
}
