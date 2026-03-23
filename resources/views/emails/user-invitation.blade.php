<x-mail::message>
# You're invited to {{ $brandName }}

**{{ $inviterName }}** has invited you to join {{ $brandName }}.

Your account will be created with the **{{ $invitation->role }}** role.

This invitation expires on **{{ $invitation->expires_at->format('F j, Y \a\t g:i A') }}**.

<x-mail::button :url="$acceptUrl">
Accept Invitation
</x-mail::button>

If you did not expect this invitation, you can safely ignore this email.

Thanks,<br>
{{ $brandName }}
</x-mail::message>
