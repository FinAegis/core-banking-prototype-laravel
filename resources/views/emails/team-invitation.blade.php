@component('mail::message')
{{-- The team name is free text chosen by the inviter. It is shown only when it meets the team name rule (no Markdown, no web address); names stored before that rule get a neutral sentence. MarkdownText::literal() still escapes every Markdown character so it renders as plain text. --}}
@if (\App\Rules\TeamName::isValid((string) $invitation->team->name))
{{ __('You have been invited to join the :team team!', ['team' => \App\Support\MarkdownText::literal($invitation->team->name)]) }}
@else
{{ __('You have been invited to join a team.') }}
@endif

@if (Laravel\Fortify\Features::enabled(Laravel\Fortify\Features::registration()))
{{ __('If you do not have an account, you may create one by clicking the button below. After creating an account, you may click the invitation acceptance button in this email to accept the team invitation:') }}

@component('mail::button', ['url' => route('register')])
{{ __('Create Account') }}
@endcomponent

{{ __('If you already have an account, you may accept this invitation by clicking the button below:') }}

@else
{{ __('You may accept this invitation by clicking the button below:') }}
@endif


@component('mail::button', ['url' => $acceptUrl])
{{ __('Accept Invitation') }}
@endcomponent

{{ __('If you did not expect to receive an invitation to this team, you may discard this email.') }}
@endcomponent
