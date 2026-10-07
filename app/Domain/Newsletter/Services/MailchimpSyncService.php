<?php

declare(strict_types=1);

namespace App\Domain\Newsletter\Services;

use App\Domain\Newsletter\Models\Subscriber;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Adds confirmed blog subscribers to the Mailchimp audience
 * (services.mailchimp.api_key / list_id).
 *
 * Called only after the double opt-in link was opened
 * ({@see SubscriberEmailService::confirm()}), so Mailchimp never holds an
 * address as `subscribed` before its owner confirmed it. Mailchimp is a
 * secondary list: a failure here is logged and never undoes the local
 * confirmation.
 */
class MailchimpSyncService
{
    /**
     * Tag the blog form adds to a subscriber.
     */
    public const BLOG_TAG = 'blog_updates';

    public function isConfigured(): bool
    {
        return $this->apiKey() !== '' && $this->listId() !== '';
    }

    /**
     * Blog subscribers: signed up through the blog form, or an existing
     * subscriber who also asked for blog updates there.
     */
    public function appliesTo(Subscriber $subscriber): bool
    {
        if ($subscriber->source === Subscriber::SOURCE_BLOG) {
            return true;
        }

        $tags = $subscriber->tags;

        return is_array($tags) && in_array(self::BLOG_TAG, $tags, true);
    }

    /**
     * Push a confirmed blog subscriber to Mailchimp as `subscribed`. An
     * address Mailchimp already holds counts as done. Never throws.
     */
    public function syncConfirmedSubscriber(Subscriber $subscriber): void
    {
        if (! $subscriber->isActive() || ! $this->appliesTo($subscriber) || ! $this->isConfigured()) {
            return;
        }

        $apiKey = $this->apiKey();
        $dataCenter = $this->dataCenter($apiKey);
        $listId = $this->listId();

        try {
            $response = Http::withBasicAuth('apikey', $apiKey)
                ->timeout(10)
                ->post(
                    "https://{$dataCenter}.api.mailchimp.com/3.0/lists/{$listId}/members",
                    [
                        'email_address' => $subscriber->email,
                        'status'        => 'subscribed',
                        'tags'          => ['blog_subscriber', 'finaegis_demo'],
                    ]
                );

            if ($response->status() === 400 && $response->json('title') === 'Member Exists') {
                Log::info('Mailchimp member already exists', ['subscriber_id' => $subscriber->id]);

                return;
            }

            $response->throw();
        } catch (RequestException $e) {
            $status = $e->response->status();

            if (in_array($status, [401, 403], true)) {
                Log::error('Mailchimp API credentials rejected', [
                    'subscriber_id' => $subscriber->id,
                    'status'        => $status,
                ]);

                return;
            }

            $this->logFailure($subscriber, $e);
        } catch (Throwable $e) {
            $this->logFailure($subscriber, $e);
        }
    }

    private function logFailure(Subscriber $subscriber, Throwable $e): void
    {
        Log::warning('Mailchimp sync failed (non-critical)', [
            'subscriber_id' => $subscriber->id,
            'error'         => $e->getMessage(),
        ]);
    }

    private function apiKey(): string
    {
        $value = config('services.mailchimp.api_key');

        return is_string($value) ? $value : '';
    }

    private function listId(): string
    {
        $value = config('services.mailchimp.list_id');

        return is_string($value) ? $value : '';
    }

    /**
     * The data center is the API key suffix after the last dash
     * (`<key>-us1`).
     */
    private function dataCenter(string $apiKey): string
    {
        $position = strrpos($apiKey, '-');
        $suffix = $position === false ? '' : substr($apiKey, $position + 1);

        return preg_match('/^[a-z]{2}\d+$/', $suffix) === 1 ? $suffix : 'us1';
    }
}
