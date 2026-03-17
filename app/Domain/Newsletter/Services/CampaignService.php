<?php

declare(strict_types=1);

namespace App\Domain\Newsletter\Services;

use App\Domain\Newsletter\Models\Campaign;
use App\Domain\Newsletter\Models\Subscriber;
use DateTimeInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Throwable;

/**
 * Campaign management service for the Newsletter domain.
 *
 * Handles campaign lifecycle: create, schedule, send, and metrics.
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
     * Send a campaign immediately.
     */
    public function sendNow(Campaign $campaign): Campaign
    {
        if ($campaign->isSent()) {
            throw new RuntimeException('Campaign has already been sent');
        }

        $campaign->update(['status' => Campaign::STATUS_SENDING]);

        $subscribers = $this->getTargetSubscribers($campaign);
        $sentCount = 0;

        foreach ($subscribers as $subscriber) {
            try {
                Mail::to($subscriber->email)->queue(
                    new \App\Domain\Newsletter\Mail\SubscriberNewsletter(
                        $subscriber,
                        $campaign->subject,
                        $campaign->content,
                    )
                );
                $sentCount++;
            } catch (Throwable $e) {
                Log::warning('Campaign email failed', [
                    'campaign'   => $campaign->uuid,
                    'subscriber' => $subscriber->uuid,
                    'error'      => $e->getMessage(),
                ]);
            }
        }

        $campaign->update([
            'status'           => Campaign::STATUS_SENT,
            'sent_at'          => now(),
            'recipients_count' => $subscribers->count(),
            'delivered_count'  => $sentCount,
        ]);

        Log::info('Campaign sent', [
            'campaign'  => $campaign->uuid,
            'total'     => $subscribers->count(),
            'delivered' => $sentCount,
        ]);

        return $campaign->fresh();
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
     * Get campaign metrics summary.
     *
     * @return array{total: int, draft: int, scheduled: int, sent: int, total_recipients: int, total_delivered: int}
     */
    public function getMetrics(): array
    {
        return [
            'total'            => Campaign::count(),
            'draft'            => Campaign::draft()->count(),
            'scheduled'        => Campaign::scheduled()->count(),
            'sent'             => Campaign::where('status', Campaign::STATUS_SENT)->count(),
            'total_recipients' => (int) Campaign::sum('recipients_count'),
            'total_delivered'  => (int) Campaign::sum('delivered_count'),
        ];
    }

    /**
     * Send all campaigns that are scheduled and past their send time.
     */
    public function sendScheduledCampaigns(): int
    {
        $campaigns = Campaign::readyToSend()->get();
        $sent = 0;

        foreach ($campaigns as $campaign) {
            $this->sendNow($campaign);
            $sent++;
        }

        return $sent;
    }

    /**
     * Get the target subscribers for a campaign.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Subscriber>
     */
    private function getTargetSubscribers(Campaign $campaign): \Illuminate\Database\Eloquent\Collection
    {
        $query = Subscriber::where('is_active', true);

        if ($campaign->segment !== null) {
            $query->where('source', $campaign->segment);
        }

        return $query->get();
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
