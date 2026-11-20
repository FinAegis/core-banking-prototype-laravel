<?php

namespace App\Domain\Newsletter\Services;

use App\Domain\Cgo\Mail\CgoNotificationReceived;
use App\Domain\Newsletter\Exceptions\SubscriptionThrottledException;
use App\Domain\Newsletter\Mail\SubscriberConfirmation;
use App\Domain\Newsletter\Mail\SubscriberNewsletter;
use App\Domain\Newsletter\Mail\SubscriberWelcome;
use App\Domain\Newsletter\Models\Subscriber;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class SubscriberEmailService
{
    /**
     * Subscription requests accepted per IP address per window.
     */
    public const MAX_REQUESTS_PER_IP = 10;

    /**
     * Subscription requests accepted per email address per window.
     */
    public const MAX_REQUESTS_PER_EMAIL = 3;

    /**
     * Throttle window (seconds). A pending subscriber is re-sent the
     * confirmation email at most once per window.
     */
    public const THROTTLE_WINDOW_SECONDS = 3600;

    /**
     * Lifetime of a confirmation link.
     */
    public const CONFIRMATION_TTL_HOURS = 48;

    /**
     * Send welcome email to a subscriber (after confirmation).
     */
    public function sendWelcomeEmail(Subscriber $subscriber): void
    {
        try {
            $mailable = $subscriber->source === Subscriber::SOURCE_CGO
                ? new CgoNotificationReceived($subscriber->email)
                : new SubscriberWelcome($subscriber);

            Mail::to($subscriber->email)->send($mailable);

            Log::info(
                'Welcome email sent to subscriber',
                [
                'subscriber_id' => $subscriber->id,
                'email'         => $subscriber->email,
                'source'        => $subscriber->source,
                ]
            );
        } catch (Exception $e) {
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
            } catch (Exception $e) {
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
        $subscriber = Subscriber::where('email', $email)->first();

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
     * Request a subscription (double opt-in).
     *
     * A new address is stored as `pending` with a hashed confirmation token
     * and receives only the confirmation email; nothing else is sent until
     * {@see confirm()} succeeds. A pending address is re-sent the
     * confirmation at most once per throttle window. An unsubscribed or
     * bounced address must confirm again before it is reactivated. An
     * active address only gets the new tags. The caller sees the same
     * outcome in every case, so the endpoint does not reveal whether an
     * address is already subscribed.
     *
     * @param  array<int, string>  $tags
     *
     * @throws SubscriptionThrottledException when the IP or the email address
     *                                        exceeded its request budget
     */
    public function subscribe(string $email, string $source, array $tags = [], ?string $ipAddress = null, ?string $userAgent = null): Subscriber
    {
        $email = trim($email);

        $this->throttle($email, $ipAddress);

        /** @var Subscriber|null $subscriber */
        $subscriber = Subscriber::query()->whereRaw('LOWER(email) = ?', [strtolower($email)])->first();

        if ($subscriber === null) {
            $subscriber = new Subscriber();
            $subscriber->fill(
                [
                    'email'        => $email,
                    'source'       => $source,
                    'status'       => Subscriber::STATUS_PENDING,
                    'tags'         => array_values($tags),
                    'ip_address'   => $ipAddress,
                    'user_agent'   => $userAgent,
                    'confirmed_at' => null,
                ]
            );
            $subscriber->save();

            $this->sendConfirmationEmail($subscriber);

            Log::info(
                'New subscriber pending confirmation',
                [
                    'subscriber_id' => $subscriber->id,
                    'source'        => $source,
                ]
            );

            return $subscriber;
        }

        if (! empty($tags)) {
            $subscriber->addTags($tags);
        }

        if ($subscriber->isActive()) {
            Log::info(
                'Existing subscriber updated',
                [
                    'subscriber_id' => $subscriber->id,
                    'source'        => $source,
                ]
            );

            return $subscriber;
        }

        // Pending, unsubscribed or bounced: (re)confirmation required.
        if (! $subscriber->isPending()) {
            $subscriber->update(['status' => Subscriber::STATUS_PENDING]);
        }

        $lastSent = $subscriber->confirmation_sent_at;
        if ($lastSent === null || $lastSent->lte(now()->subSeconds(self::THROTTLE_WINDOW_SECONDS))) {
            $this->sendConfirmationEmail($subscriber);
        }

        Log::info(
            'Subscriber pending confirmation',
            [
                'subscriber_id' => $subscriber->id,
                'source'        => $source,
            ]
        );

        return $subscriber;
    }

    /**
     * Whether the token is the subscriber's current confirmation token.
     * Changes nothing.
     */
    public function confirmationTokenMatches(Subscriber $subscriber, string $token): bool
    {
        $storedHash = $subscriber->confirmation_token_hash;

        if (! is_string($storedHash) || $storedHash === '' || $token === '') {
            return false;
        }

        return hash_equals($storedHash, hash('sha256', $token));
    }

    /**
     * Confirm a pending subscription from the emailed link. Activates the
     * subscriber and only then sends the welcome email. Returns false for an
     * unknown, already-used or replaced token.
     */
    public function confirm(Subscriber $subscriber, string $token): bool
    {
        if (! $this->confirmationTokenMatches($subscriber, $token)) {
            return false;
        }

        $subscriber->forceFill(
            [
                'status'                  => Subscriber::STATUS_ACTIVE,
                'confirmed_at'            => now(),
                'confirmation_token_hash' => null,
                'unsubscribed_at'         => null,
                'unsubscribe_reason'      => null,
            ]
        )->save();

        Log::info(
            'Subscriber confirmed',
            [
                'subscriber_id' => $subscriber->id,
                'source'        => $subscriber->source,
            ]
        );

        try {
            $this->sendWelcomeEmail($subscriber);
        } catch (Exception) {
            // Logged in sendWelcomeEmail(); the confirmation itself stands.
        }

        return true;
    }

    /**
     * Issue a fresh confirmation token (replacing any previous one) and
     * email the signed confirmation link.
     */
    private function sendConfirmationEmail(Subscriber $subscriber): void
    {
        $token = Str::random(64);

        $subscriber->forceFill(
            [
                'confirmation_token_hash' => hash('sha256', $token),
                'confirmation_sent_at'    => now(),
            ]
        )->save();

        $url = URL::temporarySignedRoute(
            'subscriber.confirm',
            now()->addHours(self::CONFIRMATION_TTL_HOURS),
            [
                'subscriber' => $subscriber->id,
                'token'      => $token,
            ]
        );

        try {
            Mail::to($subscriber->email)->send(
                new SubscriberConfirmation($subscriber, $url, self::CONFIRMATION_TTL_HOURS)
            );
        } catch (Exception $e) {
            // Allow an immediate retry: the re-send window starts only once
            // a confirmation email actually went out.
            $subscriber->forceFill(['confirmation_sent_at' => null])->save();

            Log::error(
                'Failed to send subscription confirmation email',
                [
                    'subscriber_id' => $subscriber->id,
                    'error'         => $e->getMessage(),
                ]
            );

            throw $e;
        }
    }

    /**
     * Per-IP and per-email request budget for {@see subscribe()}.
     *
     * @throws SubscriptionThrottledException
     */
    private function throttle(string $email, ?string $ipAddress): void
    {
        $keys = [
            'newsletter-subscribe:email:' . hash('sha256', strtolower($email)) => self::MAX_REQUESTS_PER_EMAIL,
        ];

        if ($ipAddress !== null && $ipAddress !== '') {
            $keys['newsletter-subscribe:ip:' . $ipAddress] = self::MAX_REQUESTS_PER_IP;
        }

        foreach ($keys as $key => $max) {
            if (RateLimiter::tooManyAttempts($key, $max)) {
                throw new SubscriptionThrottledException(RateLimiter::availableIn($key));
            }
        }

        foreach (array_keys($keys) as $key) {
            RateLimiter::hit($key, self::THROTTLE_WINDOW_SECONDS);
        }
    }
}
