<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\User\Models\UserInvitation;
use App\Domain\User\Services\UserInvitationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class InvitationController extends Controller
{
    public function __construct(
        private readonly UserInvitationService $invitationService,
    ) {
    }

    /**
     * Show the invitation acceptance form.
     */
    public function show(Request $request): View|RedirectResponse
    {
        $token = $request->query('token');

        if (! is_string($token) || $token === '') {
            return redirect('/')->with('error', 'Invalid invitation link.');
        }

        $invitation = UserInvitation::where('token', $token)->first();

        if ($invitation === null) {
            return redirect('/login')->with('error', 'Invalid invitation link.');
        }

        if ($invitation->isAccepted()) {
            return redirect('/login')->with('info', 'This invitation has already been used. Please log in.');
        }

        if ($invitation->isExpired()) {
            return redirect('/login')->with('error', 'This invitation has expired. Please contact the administrator.');
        }

        return view('auth.accept-invitation', [
            'invitation' => $invitation,
            'token'      => $token,
        ]);
    }

    /**
     * Accept the invitation and create the account.
     */
    public function accept(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'token'    => 'required|string',
            'name'     => 'required|string|max:255',
            'password' => 'required|string|min:8|confirmed',
        ]);

        try {
            $this->invitationService->accept(
                $validated['token'],
                $validated['name'],
                $validated['password'],
            );

            return redirect('/login')->with('success', 'Account created! Please log in.');
        } catch (RuntimeException $e) {
            return back()->withErrors(['token' => $e->getMessage()])->withInput();
        }
    }
}
