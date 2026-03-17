<?php

declare(strict_types=1);

namespace App\Domain\Contact\Services;

use App\Domain\Contact\Models\ContactSubmission;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

/**
 * Contact ticket management service.
 *
 * Provides assignment, response tracking, auto-responders, and status workflow.
 * Status transitions are enforced: open → assigned → responded → closed.
 */
class ContactTicketService
{
    public const STATUS_OPEN = 'open';

    public const STATUS_ASSIGNED = 'assigned';

    public const STATUS_RESPONDED = 'responded';

    public const STATUS_CLOSED = 'closed';

    /**
     * Allowed status transitions.
     *
     * @var array<string, array<int, string>>
     */
    private const TRANSITIONS = [
        self::STATUS_OPEN      => [self::STATUS_ASSIGNED, self::STATUS_CLOSED],
        self::STATUS_ASSIGNED  => [self::STATUS_RESPONDED, self::STATUS_CLOSED],
        self::STATUS_RESPONDED => [self::STATUS_CLOSED],
        self::STATUS_CLOSED    => [self::STATUS_OPEN],
    ];

    /**
     * Send auto-responder email after submission.
     * Uses Mailable for proper header sanitization (no raw Mail::raw).
     */
    public function sendAutoResponder(ContactSubmission $submission): void
    {
        $brand = config('brand.name', 'Zelta');

        // Use Mailable's built-in header sanitization instead of Mail::raw()
        Mail::send([], [], function ($message) use ($submission, $brand): void {
            $body = "Thank you for contacting {$brand} support.\n\n"
                . "We have received your message and will respond as soon as possible.\n\n"
                . "Your ticket reference: {$submission->uuid}\n\n"
                . "Best regards,\n{$brand} Support Team";

            $message->to($submission->email)
                ->subject("[{$brand}] We received your message")
                ->from(config('brand.support_email', 'support@zelta.app'), "{$brand} Support")
                ->text($body);
        });

        Log::info('Auto-responder sent', ['submission' => $submission->uuid]);
    }

    /**
     * Assign a ticket to a support agent.
     */
    public function assignTicket(ContactSubmission $submission, int $userId): ContactSubmission
    {
        $this->guardTransition($submission, self::STATUS_ASSIGNED);

        $submission->update([
            'status'      => self::STATUS_ASSIGNED,
            'assigned_to' => $userId,
        ]);

        Log::info('Ticket assigned', [
            'submission'  => $submission->uuid,
            'assigned_to' => $userId,
        ]);

        return $submission->fresh();
    }

    /**
     * Record a response.
     */
    public function respond(ContactSubmission $submission, string $responseNotes): ContactSubmission
    {
        $this->guardTransition($submission, self::STATUS_RESPONDED);

        $submission->update([
            'status'         => self::STATUS_RESPONDED,
            'response_notes' => $responseNotes,
            'responded_at'   => now(),
        ]);

        Log::info('Ticket responded', ['submission' => $submission->uuid]);

        return $submission->fresh();
    }

    /**
     * Close a ticket.
     */
    public function close(ContactSubmission $submission): ContactSubmission
    {
        $this->guardTransition($submission, self::STATUS_CLOSED);

        $submission->update(['status' => self::STATUS_CLOSED]);

        Log::info('Ticket closed', ['submission' => $submission->uuid]);

        return $submission->fresh();
    }

    /**
     * Reopen a closed ticket.
     */
    public function reopen(ContactSubmission $submission): ContactSubmission
    {
        $this->guardTransition($submission, self::STATUS_OPEN);

        $submission->update(['status' => self::STATUS_OPEN]);

        Log::info('Ticket reopened', ['submission' => $submission->uuid]);

        return $submission->fresh();
    }

    /**
     * List tickets with filtering.
     *
     * @return LengthAwarePaginator
     */
    public function list(
        ?string $status = null,
        ?string $priority = null,
        ?int $assignedTo = null,
        int $perPage = 15,
    ): LengthAwarePaginator {
        $query = ContactSubmission::query()->orderByDesc('created_at');

        if ($status !== null) {
            $query->where('status', $status);
        }

        if ($priority !== null) {
            $query->where('priority', $priority);
        }

        if ($assignedTo !== null) {
            $query->where('assigned_to', $assignedTo);
        }

        return $query->paginate($perPage);
    }

    /**
     * Get ticket statistics (single query).
     *
     * @return array{total: int, open: int, assigned: int, responded: int, closed: int}
     */
    public function getStats(): array
    {
        $stats = ContactSubmission::selectRaw("
            COUNT(*) as total,
            SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END) as open_count,
            SUM(CASE WHEN status = 'assigned' THEN 1 ELSE 0 END) as assigned_count,
            SUM(CASE WHEN status = 'responded' THEN 1 ELSE 0 END) as responded_count,
            SUM(CASE WHEN status = 'closed' THEN 1 ELSE 0 END) as closed_count
        ")->first();

        return [
            'total'     => (int) $stats->total,
            'open'      => (int) $stats->open_count,
            'assigned'  => (int) $stats->assigned_count,
            'responded' => (int) $stats->responded_count,
            'closed'    => (int) $stats->closed_count,
        ];
    }

    /**
     * Enforce valid status transitions.
     */
    private function guardTransition(ContactSubmission $submission, string $targetStatus): void
    {
        $current = $submission->status ?? self::STATUS_OPEN;
        $allowed = self::TRANSITIONS[$current] ?? [];

        if (! in_array($targetStatus, $allowed, true)) {
            throw new RuntimeException(
                "Cannot transition ticket from '{$current}' to '{$targetStatus}'"
            );
        }
    }
}
