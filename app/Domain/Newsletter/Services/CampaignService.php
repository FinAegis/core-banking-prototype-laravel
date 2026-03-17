<?php

declare(strict_types=1);

namespace App\Domain\Newsletter\Services;

use App\Domain\Newsletter\Models\Campaign;
use App\Domain\Newsletter\Models\Subscriber;
use DateTimeInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Throwable;

/**
 * Campaign management service for the Newsletter domain.
 *
 * Handles campaign lifecycle: create, schedule, send, and metrics.
 * Uses DB transactions with locking to prevent double-sends.
 */
class CampaignService
{
    /**
     * Create a new campaign draft.
     *
     * @param array{name: string, subject: string, content: string, segment?: string} $data
     */
    public function createDraft(array $data): Campaign
    {
        return Campaign::create([
            'name'    => $data['name'],
            'subject' => $data['subject'],
            'content' => $data['content'],
            'segment' => $data['segment'] ?? null,
            'status'  => Campaign::STATUS_DRAFT,
        ]);
    }

    /**
     * Schedule a campaign for future sending.
     */
    public function schedule(Campaign $campaign, DateTimeInterface $sendAt): Campaign
    {
        if (! $campaign->isDraft()) {
            throw new RuntimeException('Only draft campaigns can be scheduled');
        }

        $recipients = $this->getRecipientCount($campaign);

        $campaign->update([
            'status'           => Campaign::STATUS_SCHEDULED,
            'scheduled_at'     => $sendAt,
            'recipients_count' => $recipients,
        ]);

        Log::info('Campaign scheduled', [
            'campaign'   => $campaign->uuid,
            'send_at'    => $sendAt->format('Y-m-d H:i:s'),
            'recipients' => $recipients,
        ]);

        return $campaign->fresh();
    }

    /**
     * Send a campaign immediately, with locking to prevent double-sends.
     */
    public function sendNow(Campaign $campaign): Campaign
    {
        // Lock the campaign row to prevent concurrent sends
        return DB::transaction(function () use ($campaign): Campaign {
            /** @var Campaign $locked */
            $locked = Campaign::lockForUpdate()->find($campaign->uuid);

            if ($locked === null || $locked->isSent() || $locked->status === Campaign::STATUS_SENDING) {
                throw new RuntimeException('Campaign is already being sent or has been sent');
            }

            $locked->update(['status' => Campaign::STATUS_SENDING]);

            $queuedCount = 0;
            $totalCount = 0;

            // Use cursor() to avoid loading all subscribers into memory
            $subscribers = Subscriber::where('is_active', true);
            if ($locked->segment !== null) {
                $subscribers->where('source', $locked->segment);
            }

            foreach ($subscribers->cursor() as $subscriber) {
                $totalCount++;
                try {
                    Mail::to($subscriber->email)->queue(
                        new \App\Domain\Newsletter\Mail\SubscriberNewsletter(
                            $subscriber,
                            $locked->subject,
                            $locked->content,
                        )
                    );
                    $queuedCount++;
                } catch (Throwable $e) {
                    Log::warning('Campaign email queue failed', [
                        'campaign'   => $locked->uuid,
                        'subscriber' => $subscriber->uuid,
                        'error'      => $e->getMessage(),
                    ]);
                }
            }

            $locked->update([
                'status'           => Campaign::STATUS_SENT,
                'sent_at'          => now(),
                'recipients_count' => $totalCount,
                'delivered_count'  => $queuedCount,
            ]);

            Log::info('Campaign sent', [
                'campaign' => $locked->uuid,
                'total'    => $totalCount,
                'queued'   => $queuedCount,
            ]);

            return $locked->fresh();
        });
    }

    /**
     * Cancel a scheduled campaign.
     */
    public function cancel(Campaign $campaign): Campaign
    {
        if (! $campaign->isScheduled()) {
            throw new RuntimeException('Only scheduled campaigns can be cancelled');
        }

        $campaign->update(['status' => Campaign::STATUS_CANCELLED]);

        return $campaign->fresh();
    }

    /**
     * List campaigns with pagination.
     *
     * @return LengthAwarePaginator
     */
    public function list(?string $status = null, int $perPage = 15): LengthAwarePaginator
    {
        $query = Campaign::query()->orderByDesc('created_at');

        if ($status !== null) {
            $query->where('status', $status);
        }

        return $query->paginate($perPage);
    }

    /**
     * Get campaign metrics summary (single query).
     *
     * @return array{total: int, draft: int, scheduled: int, sent: int, total_recipients: int, total_delivered: int}
     */
    public function getMetrics(): array
    {
        $metrics = Campaign::selectRaw("
            COUNT(*) as total,
            SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as draft,
            SUM(CASE WHEN status = 'scheduled' THEN 1 ELSE 0 END) as scheduled,
            SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent,
            COALESCE(SUM(recipients_count), 0) as total_recipients,
            COALESCE(SUM(delivered_count), 0) as total_delivered
        ")->first();

        return [
            'total'            => (int) $metrics->total,
            'draft'            => (int) $metrics->draft,
            'scheduled'        => (int) $metrics->scheduled,
            'sent'             => (int) $metrics->sent,
            'total_recipients' => (int) $metrics->total_recipients,
            'total_delivered'  => (int) $metrics->total_delivered,
        ];
    }

    /**
     * Send all campaigns that are scheduled and past their send time.
     * Each campaign is isolated — one failure doesn't block others.
     */
    public function sendScheduledCampaigns(): int
    {
        $campaigns = Campaign::readyToSend()->get();
        $sent = 0;

        foreach ($campaigns as $campaign) {
            try {
                $this->sendNow($campaign);
                $sent++;
            } catch (Throwable $e) {
                Log::error('Scheduled campaign send failed', [
                    'campaign' => $campaign->uuid,
                    'error'    => $e->getMessage(),
                ]);
            }
        }

        return $sent;
    }

    /**
     * Count recipients for a campaign's segment.
     */
    private function getRecipientCount(Campaign $campaign): int
    {
        $query = Subscriber::where('is_active', true);

        if ($campaign->segment !== null) {
            $query->where('source', $campaign->segment);
        }

        return $query->count();
    }
}
