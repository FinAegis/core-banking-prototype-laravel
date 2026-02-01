<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\RegTech\Models;

use App\Domain\RegTech\Models\FilingSchedule;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FilingScheduleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-02-01 10:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_can_create_filing_schedule(): void
    {
        $schedule = FilingSchedule::create([
            'name'          => 'Quarterly SAR Report',
            'report_type'   => 'SAR',
            'jurisdiction'  => 'US',
            'regulator'     => 'FinCEN',
            'frequency'     => 'quarterly',
            'deadline_days' => 45,
        ]);

        $this->assertDatabaseHas('filing_schedules', [
            'name'         => 'Quarterly SAR Report',
            'report_type'  => 'SAR',
            'jurisdiction' => 'US',
        ]);

        $this->assertNotNull($schedule->uuid);
        $this->assertTrue($schedule->is_active);
    }

    public function test_generates_uuid_on_creation(): void
    {
        $schedule = FilingSchedule::create([
            'name'         => 'Test Schedule',
            'report_type'  => 'CTR',
            'jurisdiction' => 'US',
        ]);

        $this->assertNotNull($schedule->uuid);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/',
            $schedule->uuid
        );
    }

    public function test_active_scope(): void
    {
        FilingSchedule::create([
            'name'         => 'Active Schedule',
            'report_type'  => 'SAR',
            'jurisdiction' => 'US',
            'is_active'    => true,
        ]);

        FilingSchedule::create([
            'name'         => 'Inactive Schedule',
            'report_type'  => 'CTR',
            'jurisdiction' => 'US',
            'is_active'    => false,
        ]);

        $activeSchedules = FilingSchedule::active()->get();

        $this->assertCount(1, $activeSchedules);
        $this->assertEquals('Active Schedule', $activeSchedules->first()->name);
    }

    public function test_jurisdiction_scope(): void
    {
        FilingSchedule::create([
            'name'         => 'US Schedule',
            'report_type'  => 'SAR',
            'jurisdiction' => 'US',
        ]);

        FilingSchedule::create([
            'name'         => 'EU Schedule',
            'report_type'  => 'MiFID',
            'jurisdiction' => 'EU',
        ]);

        $usSchedules = FilingSchedule::jurisdiction('US')->get();

        $this->assertCount(1, $usSchedules);
        $this->assertEquals('US Schedule', $usSchedules->first()->name);
    }

    public function test_due_within_days_scope(): void
    {
        FilingSchedule::create([
            'name'          => 'Due Soon',
            'report_type'   => 'SAR',
            'jurisdiction'  => 'US',
            'next_due_date' => Carbon::now()->addDays(5),
        ]);

        FilingSchedule::create([
            'name'          => 'Due Later',
            'report_type'   => 'CTR',
            'jurisdiction'  => 'US',
            'next_due_date' => Carbon::now()->addDays(60),
        ]);

        $dueSoon = FilingSchedule::dueWithinDays(30)->get();

        $this->assertCount(1, $dueSoon);
        $this->assertEquals('Due Soon', $dueSoon->first()->name);
    }

    public function test_is_overdue_returns_true_when_past_due(): void
    {
        $schedule = FilingSchedule::create([
            'name'          => 'Overdue Schedule',
            'report_type'   => 'SAR',
            'jurisdiction'  => 'US',
            'next_due_date' => Carbon::now()->subDays(5),
        ]);

        $this->assertTrue($schedule->isOverdue());
    }

    public function test_is_overdue_returns_false_when_not_past_due(): void
    {
        $schedule = FilingSchedule::create([
            'name'          => 'Future Schedule',
            'report_type'   => 'SAR',
            'jurisdiction'  => 'US',
            'next_due_date' => Carbon::now()->addDays(10),
        ]);

        $this->assertFalse($schedule->isOverdue());
    }

    public function test_is_overdue_returns_false_when_no_due_date(): void
    {
        $schedule = FilingSchedule::create([
            'name'         => 'No Due Date',
            'report_type'  => 'SAR',
            'jurisdiction' => 'US',
        ]);

        $this->assertFalse($schedule->isOverdue());
    }

    public function test_days_until_due_calculation(): void
    {
        $schedule = FilingSchedule::create([
            'name'          => 'Test Schedule',
            'report_type'   => 'SAR',
            'jurisdiction'  => 'US',
            'next_due_date' => Carbon::now()->addDays(15),
        ]);

        $this->assertEquals(15, $schedule->daysUntilDue());
    }

    public function test_days_until_due_returns_null_when_no_due_date(): void
    {
        $schedule = FilingSchedule::create([
            'name'         => 'Test Schedule',
            'report_type'  => 'SAR',
            'jurisdiction' => 'US',
        ]);

        $this->assertNull($schedule->daysUntilDue());
    }

    public function test_calculate_next_due_date_for_quarterly(): void
    {
        $schedule = FilingSchedule::create([
            'name'          => 'Quarterly Report',
            'report_type'   => 'SAR',
            'jurisdiction'  => 'US',
            'frequency'     => 'quarterly',
            'deadline_days' => 45,
        ]);

        $nextDue = $schedule->calculateNextDueDate();

        $this->assertNotNull($nextDue);
        $this->assertInstanceOf(Carbon::class, $nextDue);
    }

    public function test_calculate_next_due_date_for_monthly(): void
    {
        $schedule = FilingSchedule::create([
            'name'          => 'Monthly Report',
            'report_type'   => 'CTR',
            'jurisdiction'  => 'US',
            'frequency'     => 'monthly',
            'deadline_days' => 15,
        ]);

        $nextDue = $schedule->calculateNextDueDate();

        $this->assertNotNull($nextDue);
    }

    public function test_calculate_next_due_date_for_annually(): void
    {
        $schedule = FilingSchedule::create([
            'name'          => 'Annual Report',
            'report_type'   => 'AnnualAudit',
            'jurisdiction'  => 'US',
            'frequency'     => 'annually',
            'deadline_days' => 90,
        ]);

        $nextDue = $schedule->calculateNextDueDate();

        $this->assertNotNull($nextDue);
    }

    public function test_notification_settings_are_cast_to_array(): void
    {
        $settings = ['email' => true, 'sms' => false, 'warning_days' => [7, 3, 1]];

        $schedule = FilingSchedule::create([
            'name'                  => 'Test Schedule',
            'report_type'           => 'SAR',
            'jurisdiction'          => 'US',
            'notification_settings' => $settings,
        ]);

        $this->assertIsArray($schedule->notification_settings);
        $this->assertEquals($settings, $schedule->notification_settings);
    }

    public function test_metadata_are_cast_to_array(): void
    {
        $metadata = ['custom_field' => 'value', 'priority' => 'high'];

        $schedule = FilingSchedule::create([
            'name'         => 'Test Schedule',
            'report_type'  => 'SAR',
            'jurisdiction' => 'US',
            'metadata'     => $metadata,
        ]);

        $this->assertIsArray($schedule->metadata);
        $this->assertEquals($metadata, $schedule->metadata);
    }

    public function test_soft_delete(): void
    {
        $schedule = FilingSchedule::create([
            'name'         => 'To Be Deleted',
            'report_type'  => 'SAR',
            'jurisdiction' => 'US',
        ]);

        $schedule->delete();

        $this->assertSoftDeleted('filing_schedules', ['id' => $schedule->id]);
        $this->assertCount(0, FilingSchedule::all());
        $this->assertCount(1, FilingSchedule::withTrashed()->get());
    }

    public function test_default_values(): void
    {
        $schedule = FilingSchedule::create([
            'name'         => 'Minimal Schedule',
            'report_type'  => 'SAR',
            'jurisdiction' => 'US',
        ]);

        $this->assertEquals('default', $schedule->regulator);
        $this->assertEquals('quarterly', $schedule->frequency);
        $this->assertEquals(30, $schedule->deadline_days);
        $this->assertTrue($schedule->is_active);
        $this->assertFalse($schedule->auto_generate);
    }
}
