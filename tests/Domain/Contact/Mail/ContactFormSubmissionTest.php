<?php

declare(strict_types=1);

namespace Tests\Domain\Contact\Mail;

use App\Domain\Contact\Mail\ContactFormSubmission;
use App\Domain\Contact\Models\ContactSubmission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContactFormSubmissionTest extends TestCase
{
    use RefreshDatabase;

    /** @param array<string, mixed> $overrides */
    private function createSubmission(array $overrides = []): ContactSubmission
    {
        return ContactSubmission::create(array_merge([
            'name'       => 'Jane Smith',
            'email'      => 'jane@example.com',
            'subject'    => 'billing',
            'message'    => 'I have a billing question.',
            'priority'   => 'high',
            'ip_address' => '10.0.0.1',
            'user_agent' => 'PHPUnit',
            'status'     => 'pending',
        ], $overrides));
    }

    public function test_envelope_has_correct_subject_line(): void
    {
        $submission = $this->createSubmission(['priority' => 'urgent', 'subject' => 'api']);
        $mailable = new ContactFormSubmission($submission);

        $envelope = $mailable->envelope();

        $this->assertStringContainsString('[FinAegis Contact]', (string) $envelope->subject);
        $this->assertStringContainsString('Urgent', (string) $envelope->subject);
        $this->assertStringContainsString('API & Integration', (string) $envelope->subject);
    }

    public function test_envelope_has_reply_to_set_to_submitter_email(): void
    {
        $submission = $this->createSubmission(['email' => 'test@example.org']);
        $mailable = new ContactFormSubmission($submission);

        $envelope = $mailable->envelope();

        $found = false;
        foreach ($envelope->replyTo as $address) {
            if (is_string($address) && $address === 'test@example.org') {
                $found = true;
            } elseif (is_object($address) && $address->address === 'test@example.org') {
                $found = true;
            }
        }
        $this->assertTrue($found, 'Reply-to should be set to submitter email');
    }

    public function test_content_uses_markdown_template(): void
    {
        $submission = $this->createSubmission();
        $mailable = new ContactFormSubmission($submission);

        $content = $mailable->content();

        $this->assertEquals('emails.contact-form-submission', $content->markdown);
    }

    public function test_attachments_are_empty_when_no_attachment(): void
    {
        $submission = $this->createSubmission(['attachment_path' => null]);
        $mailable = new ContactFormSubmission($submission);

        $this->assertEmpty($mailable->attachments());
    }

    public function test_mailable_is_renderable(): void
    {
        $submission = $this->createSubmission();
        $mailable = new ContactFormSubmission($submission);

        $mailable->assertSeeInOrderInHtml([]);
    }
}
