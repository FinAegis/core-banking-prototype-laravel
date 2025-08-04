<?php

namespace App\Domain\Newsletter\Services;

use App\Domain\Newsletter\Mail\SubscriberNewsletter;
use App\Domain\Newsletter\Mail\SubscriberWelcome;
use App\Domain\Newsletter\Models\Subscriber;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SubscriberEmailService
{
    /**
     * Send welcome email to new subscriber.
     */
    public function sendWelcomeEmail(Subscriber $subscriber): void
    {
        try {
            Mail::to($subscriber->email)->send(new SubscriberWelcome($subscriber));

            Log::info(
                'Welcome email sent to subscriber',
                [
                'subscriber_id' => $subscriber->id,
                'email'         => $subscriber->email,
                'source'        => $subscriber->source,
                ]
            );
        } catch (\Exception $e) {
            Log::error(
                'Failed to send welcome email',
                [
                'subscriber_id' => $subscriber->id,
                'email'         => $subscriber->email,
                'error'         => $e->getMessage(),
                ]
            );

            throw $e;
        }
    }

    /**
     * Send newsletter to active subscribers.
     */
    public function sendNewsletter(string $subject, string $content, array $tags = [], ?string $source = null): int
    {
        $query = Subscriber::active();

        if (! empty($tags)) {
            $query->where(
                function ($q) use ($tags) {
                    foreach ($tags as $tag) {
                        $q->orWhereJsonContains('tags', $tag);
                    }
                }
            );
        }

        if ($source) {
            $query->bySource($source);
        }

        $subscribers = $query->get();
        $sentCount = 0;

        foreach ($subscribers as $subscriber) {
            try {
                Mail::to($subscriber->email)->send(new SubscriberNewsletter($subscriber, $subject, $content));
                $sentCount++;

                Log::info(
                    'Newsletter sent to subscriber',
                    [
                    'subscriber_id' => $subscriber->id,
                    'email'         => $subscriber->email,
                    'subject'       => $subject,
                    ]
                );
            } catch (\Exception $e) {
                Log::error(
                    'Failed to send newsletter to subscriber',
                    [
                    'subscriber_id' => $subscriber->id,
                    'email'         => $subscriber->email,
                    'subject'       => $subject,
                    'error'         => $e->getMessage(),
                    ]
                );
            }
        }

        Log::info(
            'Newsletter campaign completed',
            [
            'subject'          => $subject,
            'total_recipients' => $subscribers->count(),
            'sent_count'       => $sentCount,
            'tags'             => $tags,
            'source'           => $source,
            ]
        );

        return $sentCount;
    }

    /**
     * Handle email bounce.
     */
    public function handleBounce(string $email): void
    {
        /** @var mixed|null $subscriber */
        $subscriber = null;
        /** @var \Illuminate\Database\Eloquent\Model|null $$subscriber */
        $$subscriber = Subscriber::where('email', $email)->first();

        if ($subscriber) {
            $subscriber->update(
                [
                'status'             => Subscriber::STATUS_BOUNCED,
                'unsubscribed_at'    => now(),
                'unsubscribe_reason' => 'Email bounced',
                ]
            );

            Log::warning(
                'Subscriber marked as bounced',
                [
                'subscriber_id' => $subscriber->id,
                'email'         => $email,
                ]
            );
        }
    }

    /**
     * Process unsubscribe request.
     */
    public function processUnsubscribe(string $email, ?string $reason = null): bool
    {
        /** @var Subscriber|null $subscriber */
        $subscriber = Subscriber::where('email', $email)->first();

        if ($subscriber && $subscriber->isActive()) {
            $subscriber->unsubscribe($reason);

            Log::info(
                'Subscriber unsubscribed',
                [
                'subscriber_id' => $subscriber->id,
                'email'         => $email,
                'reason'        => $reason,
                ]
            );

            return true;
        }

        return false;
    }

    /**
     * Subscribe or update existing subscriber.
     */
    public function subscribe(string $email, string $source, array $tags = [], ?string $ipAddress = null, ?string $userAgent = null): Subscriber
    {
        $subscriber = Subscriber::firstOrNew(['email' => $email]);

        if ($subscriber->exists) {
            // Reactivate if unsubscribed
            if (! $subscriber->isActive()) {
                $subscriber->update(
                    [
                    'status'             => Subscriber::STATUS_ACTIVE,
                    'unsubscribed_at'    => null,
                    'unsubscribe_reason' => null,
                    ]
                );
            }

            // Add new tags
            if (! empty($tags)) {
                $subscriber->addTags($tags);
            }

            Log::info(
                'Existing subscriber reactivated or updated',
                [
                'subscriber_id' => $subscriber->id,
                'email'         => $email,
                'source'        => $source,
                ]
            );
        } else {
            // New subscriber
            $subscriber->fill(
                [
                'source'       => $source,
                'status'       => Subscriber::STATUS_ACTIVE,
                'tags'         => $tags,
                'ip_address'   => $ipAddress,
                'user_agent'   => $userAgent,
                'confirmed_at' => now(),
                ]
            );

            $subscriber->save();

            // Send welcome email
            $this->sendWelcomeEmail($subscriber);

            Log::info(
                'New subscriber created',
                [
                'subscriber_id' => $subscriber->id,
                'email'         => $email,
                'source'        => $source,
                ]
            );
        }

        return $subscriber;
    }
}
