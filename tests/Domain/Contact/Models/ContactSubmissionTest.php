<?php

declare(strict_types=1);

namespace Tests\Domain\Contact\Models;

use App\Domain\Contact\Models\ContactSubmission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContactSubmissionTest extends TestCase
{
    use RefreshDatabase;

    /** @param array<string, mixed> $overrides */
    private function createSubmission(array $overrides = []): ContactSubmission
    {
        return ContactSubmission::create(array_merge([
            'name'       => 'John Doe',
            'email'      => 'john@example.com',
            'subject'    => 'technical',
            'message'    => 'I need help with my account.',
            'priority'   => 'medium',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'status'     => 'pending',
        ], $overrides));
    }

    public function test_it_has_correct_fillable_attributes(): void
    {
        $submission = $this->createSubmission();

        $this->assertDatabaseHas('contact_submissions', [
            'name'    => 'John Doe',
            'email'   => 'john@example.com',
            'subject' => 'technical',
            'status'  => 'pending',
        ]);
    }

    public function test_subject_label_returns_mapped_label(): void
    {
        $cases = [
            'account'    => 'Account Issues',
            'technical'  => 'Technical Support',
            'billing'    => 'Billing & Payments',
            'gcu'        => 'GCU Questions',
            'api'        => 'API & Integration',
            'compliance' => 'Compliance & Security',
            'other'      => 'Other',
        ];

        foreach ($cases as $subject => $expectedLabel) {
            $submission = $this->createSubmission(['subject' => $subject]);
            $this->assertEquals($expectedLabel, $submission->subject_label, "Failed for subject: {$subject}");
        }
    }

    public function test_subject_label_returns_unknown_for_invalid_subject(): void
    {
        $submission = $this->createSubmission(['subject' => 'nonexistent']);
        $this->assertEquals('Unknown', $submission->subject_label);
    }

    public function test_priority_color_returns_correct_color(): void
    {
        $cases = [
            'low'    => 'gray',
            'medium' => 'yellow',
            'high'   => 'orange',
            'urgent' => 'red',
        ];

        foreach ($cases as $priority => $expectedColor) {
            $submission = $this->createSubmission(['priority' => $priority]);
            $this->assertEquals($expectedColor, $submission->priority_color, "Failed for priority: {$priority}");
        }
    }

    public function test_priority_color_returns_gray_for_unknown_priority(): void
    {
        $submission = new ContactSubmission(['priority' => 'unknown']);
        $this->assertEquals('gray', $submission->priority_color);
    }

    public function test_mark_as_responded_updates_status_and_timestamp(): void
    {
        $submission = $this->createSubmission();

        $submission->markAsResponded('Issue resolved via email.');

        $submission->refresh();
        $this->assertEquals('responded', $submission->status);
        $this->assertNotNull($submission->responded_at);
        $this->assertEquals('Issue resolved via email.', $submission->response_notes);
    }

    public function test_mark_as_responded_without_notes(): void
    {
        $submission = $this->createSubmission();

        $submission->markAsResponded();

        $submission->refresh();
        $this->assertEquals('responded', $submission->status);
        $this->assertNull($submission->response_notes);
    }

    public function test_responded_at_is_cast_to_datetime(): void
    {
        $submission = $this->createSubmission();
        $submission->markAsResponded();
        $submission->refresh();

        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $submission->responded_at);
    }
}
