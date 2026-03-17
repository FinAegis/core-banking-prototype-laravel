<?php

declare(strict_types=1);

namespace App\Domain\Contact\Services;

use App\Domain\Contact\Models\ContactSubmission;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Contact ticket management service.
 *
 * Provides assignment, response tracking, auto-responders, and status workflow
 * for support ticket handling.
 */
class ContactTicketService
{
    public const STATUS_OPEN = 'open';

    public const STATUS_ASSIGNED = 'assigned';

    public const STATUS_RESPONDED = 'responded';

    public const STATUS_CLOSED = 'closed';

    /**
     * Send auto-responder email after submission.
     */
    public function sendAutoResponder(ContactSubmission $submission): void
    {
        $brand = config('brand.name', 'Zelta');

        Mail::raw(
            "Thank you for contacting {$brand} support.\n\n"
            . "We have received your message regarding \"{$submission->subject_label}\" and will respond as soon as possible.\n\n"
            . "Your ticket reference: {$submission->uuid}\n"
            . "Priority: {$submission->priority}\n\n"
            . "Best regards,\n{$brand} Support Team",
            function ($message) use ($submission, $brand): void {
                $message->to($submission->email)
                    ->subject("[{$brand}] We received your message — #{$submission->uuid}")
                    ->from(config('brand.support_email', 'support@zelta.app'), "{$brand} Support");
            }
        );

        Log::info('Auto-responder sent', ['submission' => $submission->uuid]);
    }

    /**
     * Assign a ticket to a support agent.
     */
    public function assignTicket(ContactSubmission $submission, int $userId): ContactSubmission
    {
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
     * Record a response and notify the submitter.
     */
    public function respond(ContactSubmission $submission, string $responseNotes): ContactSubmission
    {
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
        $submission->update(['status' => self::STATUS_CLOSED]);

        return $submission->fresh();
    }

    /**
     * Reopen a closed ticket.
     */
    public function reopen(ContactSubmission $submission): ContactSubmission
    {
        $submission->update(['status' => self::STATUS_OPEN]);

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
     * Get ticket statistics.
     *
     * @return array{total: int, open: int, assigned: int, responded: int, closed: int, by_priority: array<string, int>}
     */
    public function getStats(): array
    {
        return [
            'total'       => ContactSubmission::count(),
            'open'        => ContactSubmission::where('status', self::STATUS_OPEN)->count(),
            'assigned'    => ContactSubmission::where('status', self::STATUS_ASSIGNED)->count(),
            'responded'   => ContactSubmission::where('status', self::STATUS_RESPONDED)->count(),
            'closed'      => ContactSubmission::where('status', self::STATUS_CLOSED)->count(),
            'by_priority' => ContactSubmission::selectRaw('priority, COUNT(*) as count')
                ->groupBy('priority')
                ->pluck('count', 'priority')
                ->toArray(),
        ];
    }
}
