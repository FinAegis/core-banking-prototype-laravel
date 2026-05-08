<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\InvestorInquiryRequest;
use App\Models\InvestorInquiry;
use App\Notifications\InvestorInquirySubmitted;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * Public investor lead-capture controller for /invest.
 *
 * The page itself is statically rendered Blade. Submissions persist a row
 * to `investor_inquiries`, queue a founder notification, and redirect to
 * a thanks page. Honeypot submissions are silently swallowed (no DB write,
 * no notification, but the user sees the same thanks page so a bot can't
 * differentiate).
 *
 * Data-room access is gated; for now the route returns a 403-style "request
 * access" page. Manual approval will be added in a later iteration.
 */
final class InvestorController extends Controller
{
    public function show(): View
    {
        return view('invest');
    }

    public function submit(InvestorInquiryRequest $request): RedirectResponse
    {
        // Honeypot — silently swallow without writing to DB. Do NOT return a
        // validation error: that would tip off the bot. We pretend success.
        if ($request->filled('website')) {
            return redirect()->route('invest.thanks');
        }

        $validated = $request->validated();

        $inquiry = InvestorInquiry::create([
            'name'             => (string) $validated['name'],
            'email'            => (string) $validated['email'],
            'linkedin_url'     => (string) $validated['linkedin_url'],
            'investing_as'     => (string) $validated['investing_as'],
            'path_of_interest' => (string) $validated['path_of_interest'],
            'check_size_range' => (string) $validated['check_size_range'],
            'questions'        => isset($validated['questions']) && $validated['questions'] !== ''
                ? (string) $validated['questions']
                : null,
            'ip_hash'    => $this->hashIp((string) ($request->ip() ?? '')),
            'user_agent' => substr((string) ($request->userAgent() ?? ''), 0, 500),
        ]);

        // Email failure must not break the user-facing submission flow.
        // The notification is queued (ShouldQueue), so even if dispatch fails
        // synchronously we still want the submission persisted.
        try {
            Notification::route('mail', (string) config('investor.notification_email'))
                ->notify(new InvestorInquirySubmitted($inquiry));
        } catch (Throwable $e) {
            report($e);
        }

        return redirect()->route('invest.thanks');
    }

    public function thanks(): View
    {
        return view('invest.thanks');
    }

    public function dataRoom(): \Illuminate\Http\Response
    {
        return response()->view('invest.data-room-gated', [], 403);
    }

    private function hashIp(string $ip): string
    {
        return hash('sha256', $ip . '|' . (string) config('app.key'));
    }
}
