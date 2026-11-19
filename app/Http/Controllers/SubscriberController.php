<?php

namespace App\Http\Controllers;

use App\Domain\Newsletter\Exceptions\SubscriptionThrottledException;
use App\Domain\Newsletter\Models\Subscriber;
use App\Domain\Newsletter\Services\MailchimpSyncService;
use App\Domain\Newsletter\Services\SubscriberEmailService;
use Exception;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Tag(
    name: 'Newsletter',
    description: 'Newsletter subscription management'
)]
class SubscriberController extends Controller
{
    public function __construct(
        private SubscriberEmailService $emailService,
        private MailchimpSyncService $mailchimp,
    ) {
    }

        #[OA\Get(
            path: '/newsletter/unsubscribe',
            operationId: 'newsletterUnsubscribe',
            tags: ['Newsletter'],
            summary: 'Unsubscribe from newsletter',
            description: 'Unsubscribes an email from the newsletter'
        )]
    #[OA\Response(
        response: 200,
        description: 'Successful operation'
    )]
    #[OA\Response(
        response: 500,
        description: 'Server error'
    )]
    public function unsubscribe(Request $request, string $encryptedEmail)
    {
        try {
            $email = decrypt($encryptedEmail);

            $unsubscribed = $this->emailService->processUnsubscribe($email, 'User requested unsubscribe');

            if ($unsubscribed) {
                return view(
                    'subscriber.unsubscribed',
                    [
                        'message' => 'You have been successfully unsubscribed from our mailing list.',
                    ]
                );
            }

            return view(
                'subscriber.unsubscribed',
                [
                    'message' => 'You are already unsubscribed or we could not find your subscription.',
                ]
            );
        } catch (Exception $e) {
            return view(
                'subscriber.unsubscribed',
                [
                    'message' => 'Invalid unsubscribe link. Please contact support if you need assistance.',
                ]
            );
        }
    }

        #[OA\Post(
            path: '/newsletter/subscribe',
            operationId: 'newsletterSubscribe',
            tags: ['Newsletter'],
            summary: 'Subscribe to newsletter',
            description: 'Subscribes an email to the newsletter'
        )]
    #[OA\Response(
        response: 201,
        description: 'Successful operation'
    )]
    #[OA\Response(
        response: 500,
        description: 'Server error'
    )]
    public function subscribe(Request $request, string $source)
    {
        $validated = $request->validate(
            [
                'email' => 'required|email',
                'tags'  => 'array',
            ]
        );

        try {
            $this->emailService->subscribe(
                $validated['email'],
                $source,
                $validated['tags'] ?? [],
                $request->ip(),
                $request->userAgent()
            );

            return response()->json(
                [
                    'success' => true,
                    'message' => 'Almost done! Check your inbox and confirm your email address to subscribe.',
                ]
            );
        } catch (SubscriptionThrottledException $e) {
            return response()->json(
                [
                    'success' => false,
                    'message' => 'Too many requests. Please try again later.',
                ],
                429,
                ['Retry-After' => (string) max(1, $e->retryAfterSeconds)]
            );
        } catch (Exception $e) {
            return response()->json(
                [
                    'success' => false,
                    'message' => 'An error occurred. Please try again later.',
                ],
                500
            );
        }
    }

    /**
     * Show the page behind the signed link in the confirmation email (double
     * opt-in). Opening the link changes nothing, because mail security
     * scanners open links on their own. The page's button (a CSRF-protected
     * POST to the same signed URL) confirms.
     */
    public function showConfirm(Request $request, string $subscriber): View
    {
        $record = $this->subscriberForSignedLink($request, $subscriber);

        if ($record === null) {
            return $this->invalidConfirmationView();
        }

        if ($record->isActive() && $record->confirmation_token_hash === null) {
            return $this->alreadyConfirmedView();
        }

        if (! $this->emailService->confirmationTokenMatches($record, (string) $request->query('token', ''))) {
            return $this->invalidConfirmationView();
        }

        return view(
            'subscriber.confirmed',
            [
                'title'      => 'Confirm your subscription',
                'message'    => 'Press the button below to confirm your email address. You will then receive our updates.',
                'confirmUrl' => $request->getRequestUri(),
            ]
        );
    }

    /**
     * Confirm a subscription (POST from the page above, same signed URL).
     * Only a valid, unexpired link activates the subscriber; the welcome
     * email is sent at that point.
     */
    public function confirm(Request $request, string $subscriber): View
    {
        $record = $this->subscriberForSignedLink($request, $subscriber);

        if ($record === null) {
            return $this->invalidConfirmationView();
        }

        if ($record->isActive() && $record->confirmation_token_hash === null) {
            return $this->alreadyConfirmedView();
        }

        if (! $this->emailService->confirm($record, (string) $request->query('token', ''))) {
            return $this->invalidConfirmationView();
        }

        // Blog subscribers reach the Mailchimp audience only once confirmed.
        $this->mailchimp->syncConfirmedSubscriber($record);

        return view(
            'subscriber.confirmed',
            [
                'title'   => 'Subscription confirmed',
                'message' => 'Thanks for confirming your email address. You are now subscribed.',
            ]
        );
    }

    /**
     * The subscriber named by a correctly signed, unexpired link, or null.
     */
    private function subscriberForSignedLink(Request $request, string $subscriber): ?Subscriber
    {
        if (! $request->hasValidSignature()) {
            return null;
        }

        /** @var Subscriber|null $record */
        $record = Subscriber::query()->find($subscriber);

        return $record;
    }

    private function invalidConfirmationView(): View
    {
        return view(
            'subscriber.confirmed',
            [
                'title'   => 'Link expired or invalid',
                'message' => 'This confirmation link is no longer valid. Subscribe again to get a new confirmation link.',
            ]
        );
    }

    private function alreadyConfirmedView(): View
    {
        return view(
            'subscriber.confirmed',
            [
                'title'   => 'Already confirmed',
                'message' => 'Your email address is already confirmed. Thanks for subscribing!',
            ]
        );
    }
}
