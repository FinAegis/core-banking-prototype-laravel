<?php

declare(strict_types=1);

namespace App\Domain\RegTech\Services;

use App\Domain\RegTech\Enums\Jurisdiction;
use App\Domain\RegTech\Models\FilingSchedule;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Manages regulatory calendar, deadlines, and filing schedules.
 */
class RegulatoryCalendarService
{
    public function __construct(
        private readonly JurisdictionConfigurationService $jurisdictionService
    ) {
    }

    /**
     * Get upcoming deadlines.
     *
     * @param int $days
     * @param string|null $jurisdiction
     * @return Collection<int, FilingSchedule>
     */
    public function getUpcomingDeadlines(int $days = 30, ?string $jurisdiction = null): Collection
    {
        $query = FilingSchedule::active()->dueWithinDays($days);

        if ($jurisdiction) {
            $query->jurisdiction($jurisdiction);
        }

        return $query->orderBy('next_due_date')->get();
    }

    /**
     * Get overdue filings.
     *
     * @param string|null $jurisdiction
     * @return Collection<int, FilingSchedule>
     */
    public function getOverdueFilings(?string $jurisdiction = null): Collection
    {
        $query = FilingSchedule::active()
            ->whereNotNull('next_due_date')
            ->where('next_due_date', '<', now());

        if ($jurisdiction) {
            $query->jurisdiction($jurisdiction);
        }

        return $query->orderBy('next_due_date')->get();
    }

    /**
     * Get filing schedule for a report type.
     *
     * @param string $reportType
     * @param string|null $jurisdiction
     * @return FilingSchedule|null
     */
    public function getFilingSchedule(string $reportType, ?string $jurisdiction = null): ?FilingSchedule
    {
        $query = FilingSchedule::where('report_type', $reportType);

        if ($jurisdiction) {
            $query->jurisdiction($jurisdiction);
        }

        return $query->first();
    }

    /**
     * Calculate deadline for a filing.
     *
     * @param string $reportType
     * @param Jurisdiction|string $jurisdiction
     * @param Carbon|null $referenceDate
     * @return Carbon
     */
    public function calculateDeadline(
        string $reportType,
        Jurisdiction|string $jurisdiction,
        ?Carbon $referenceDate = null
    ): Carbon {
        $scheduleConfig = config("regtech.filing_schedules.{$reportType}");
        $referenceDate = $referenceDate ?? now();

        if (! $scheduleConfig) {
            // Default to 30 days
            return $referenceDate->copy()->addDays(30);
        }

        $deadlineDays = $scheduleConfig['deadline'] ?? 30;
        $frequency = $scheduleConfig['frequency'] ?? 'event';

        $deadline = match ($frequency) {
            'daily'       => $referenceDate->copy()->addDay()->endOfDay()->addDays($deadlineDays - 1),
            'weekly'      => $referenceDate->copy()->endOfWeek()->addDays($deadlineDays),
            'monthly'     => $referenceDate->copy()->endOfMonth()->addDays($deadlineDays),
            'quarterly'   => $this->getQuarterEndDeadline($referenceDate, $deadlineDays),
            'annually'    => $referenceDate->copy()->endOfYear()->addDays($deadlineDays),
            'transaction' => $referenceDate->copy()->addDays($deadlineDays),
            'event'       => $referenceDate->copy()->addDays($deadlineDays),
            default       => $referenceDate->copy()->addDays($deadlineDays),
        };

        // Adjust for holidays
        $key = $jurisdiction instanceof Jurisdiction ? $jurisdiction->value : $jurisdiction;
        $deadline = $this->adjustForHolidays($deadline, $key);

        return $deadline;
    }

    /**
     * Get quarter end deadline.
     */
    private function getQuarterEndDeadline(Carbon $date, int $deadlineDays): Carbon
    {
        $quarterEnd = $date->copy()->endOfQuarter();

        if ($date->gt($quarterEnd)) {
            $quarterEnd = $date->copy()->addQuarterNoOverflow()->endOfQuarter();
        }

        return $quarterEnd->addDays($deadlineDays);
    }

    /**
     * Adjust deadline for holidays.
     *
     * @param Carbon $deadline
     * @param string $jurisdiction
     * @return Carbon
     */
    public function adjustForHolidays(Carbon $deadline, string $jurisdiction): Carbon
    {
        $holidays = config("regtech.calendar.holidays.{$jurisdiction}", []);

        // Convert to Carbon dates for current year
        $holidayDates = collect($holidays)->map(function ($holiday) use ($deadline) {
            return Carbon::parse($deadline->year . '-' . $holiday);
        });

        // If deadline falls on weekend, move to next business day
        while ($deadline->isWeekend() || $holidayDates->contains($deadline->toDateString())) {
            $deadline->addDay();
        }

        return $deadline;
    }

    /**
     * Check if date is a business day.
     *
     * @param Carbon $date
     * @param string $jurisdiction
     * @return bool
     */
    public function isBusinessDay(Carbon $date, string $jurisdiction): bool
    {
        if ($date->isWeekend()) {
            return false;
        }

        $holidays = config("regtech.calendar.holidays.{$jurisdiction}", []);
        $holidayDate = $date->format('m-d');

        return ! in_array($holidayDate, $holidays);
    }

    /**
     * Get next business day.
     *
     * @param Carbon $date
     * @param string $jurisdiction
     * @return Carbon
     */
    public function getNextBusinessDay(Carbon $date, string $jurisdiction): Carbon
    {
        $nextDay = $date->copy();

        while (! $this->isBusinessDay($nextDay, $jurisdiction)) {
            $nextDay->addDay();
        }

        return $nextDay;
    }

    /**
     * Get business days between two dates.
     *
     * @param Carbon $startDate
     * @param Carbon $endDate
     * @param string $jurisdiction
     * @return int
     */
    public function getBusinessDaysBetween(Carbon $startDate, Carbon $endDate, string $jurisdiction): int
    {
        $days = 0;
        $current = $startDate->copy();

        while ($current->lt($endDate)) {
            if ($this->isBusinessDay($current, $jurisdiction)) {
                $days++;
            }
            $current->addDay();
        }

        return $days;
    }

    /**
     * Get quarter dates.
     *
     * @param int|null $year
     * @return array<int, array{start: Carbon, end: Carbon}>
     */
    public function getQuarterDates(?int $year = null): array
    {
        $year = $year ?? now()->year;

        return [
            1 => [
                'start' => Carbon::create($year, 1, 1)->startOfDay(),
                'end'   => Carbon::create($year, 3, 31)->endOfDay(),
            ],
            2 => [
                'start' => Carbon::create($year, 4, 1)->startOfDay(),
                'end'   => Carbon::create($year, 6, 30)->endOfDay(),
            ],
            3 => [
                'start' => Carbon::create($year, 7, 1)->startOfDay(),
                'end'   => Carbon::create($year, 9, 30)->endOfDay(),
            ],
            4 => [
                'start' => Carbon::create($year, 10, 1)->startOfDay(),
                'end'   => Carbon::create($year, 12, 31)->endOfDay(),
            ],
        ];
    }

    /**
     * Get current quarter.
     *
     * @return int
     */
    public function getCurrentQuarter(): int
    {
        return (int) ceil(now()->month / 3);
    }

    /**
     * Get schedules requiring notification.
     *
     * @return Collection<int, array{schedule: FilingSchedule, days_until_due: int}>
     */
    public function getSchedulesRequiringNotification(): Collection
    {
        $warningDays = config('regtech.notifications.deadline_warning_days', [7, 3, 1]);
        $maxWarningDays = max($warningDays);

        $schedules = FilingSchedule::active()
            ->dueWithinDays($maxWarningDays)
            ->get();

        return $schedules->map(function ($schedule) use ($warningDays) {
            $daysUntilDue = $schedule->daysUntilDue();

            if (in_array($daysUntilDue, $warningDays)) {
                return [
                    'schedule'       => $schedule,
                    'days_until_due' => $daysUntilDue,
                ];
            }

            return null;
        })->filter();
    }

    /**
     * Create or update filing schedule.
     *
     * @param array<string, mixed> $data
     * @return FilingSchedule
     */
    public function upsertFilingSchedule(array $data): FilingSchedule
    {
        $schedule = FilingSchedule::updateOrCreate(
            [
                'report_type'  => $data['report_type'],
                'jurisdiction' => $data['jurisdiction'],
                'regulator'    => $data['regulator'] ?? 'default',
            ],
            [
                'name'                  => $data['name'] ?? $data['report_type'],
                'frequency'             => $data['frequency'] ?? 'quarterly',
                'deadline_days'         => $data['deadline_days'] ?? 30,
                'deadline_time'         => $data['deadline_time'] ?? null,
                'next_due_date'         => $data['next_due_date'] ?? null,
                'is_active'             => $data['is_active'] ?? true,
                'auto_generate'         => $data['auto_generate'] ?? false,
                'notification_settings' => $data['notification_settings'] ?? null,
                'metadata'              => $data['metadata'] ?? null,
            ]
        );

        // Calculate next due date if not provided
        if (! $schedule->next_due_date) {
            $schedule->update([
                'next_due_date' => $schedule->calculateNextDueDate(),
            ]);
        }

        return $schedule;
    }

    /**
     * Seed default filing schedules from config.
     */
    public function seedDefaultSchedules(): void
    {
        $scheduleConfigs = config('regtech.filing_schedules', []);

        foreach ($scheduleConfigs as $reportType => $config) {
            $jurisdictions = (array) ($config['jurisdiction'] ?? ['US']);

            foreach ($jurisdictions as $jurisdiction) {
                $this->upsertFilingSchedule([
                    'report_type'   => $reportType,
                    'jurisdiction'  => $jurisdiction,
                    'name'          => $reportType,
                    'frequency'     => $config['frequency'] ?? 'quarterly',
                    'deadline_days' => $config['deadline'] ?? 30,
                ]);
            }
        }
    }
}
