<?php

namespace App\Http\Controllers;

use App\Domain\Newsletter\Services\SubscriberEmailService;
use Exception;
use Illuminate\Http\Request;

class SubscriberController extends Controller
{
    public function __construct(
        private SubscriberEmailService $emailService
    ) {
    }

    /**
     * Handle unsubscribe request.
     */
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

    /**
     * Handle subscription from various forms.
     */
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
                    'message' => 'Thank you for subscribing! Please check your email.',
                ]
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
}
