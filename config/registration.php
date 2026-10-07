<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| API user registration
|--------------------------------------------------------------------------
|
| Governs every API path that creates a new user account:
|   - POST /api/auth/register and POST /api/v2/auth/register (password
|     sign-up, RegisterController -> CreateNewUser);
|   - first login with a new Privy identity (POST /api/v1/auth/privy-login)
|     and the web Privy email-OTP login (/login/privy/verify), which
|     auto-create the account;
|   - first social login (POST /api/auth/social/{provider}/callback).
| Existing users always keep logging in. The Fortify web registration form
| is governed separately by REGISTRATION_ENABLED (config/fortify.php).
| Not covered: admin-issued invitations accepted on the web
| (/invitation/accept), business team members added by an existing user
| (TeamMemberController) and operator CLI commands (user:create,
| account:provision-reviewer).
|
| api_enabled: secure by default — OFF when APP_ENV=production (or unset),
| ON for every other environment; an explicit REGISTRATION_API_ENABLED value
| always wins. When off, new-account requests answer 403 ERR_REG_001.
|
| api_invite_only: only applies while api_enabled is on. A new account then
| needs a pending, unexpired invitation (user_invitations, created by an
| admin) for the same email address — for Privy, the Privy-verified email.
| Without one the request answers 403 ERR_REG_002. The invitation is marked
| accepted when the account is created; its role is not applied (API
| sign-ups get the regular customer role).
|
*/

return [
    'api_enabled' => (bool) env('REGISTRATION_API_ENABLED', env('APP_ENV', 'production') !== 'production'),

    'api_invite_only' => (bool) env('REGISTRATION_API_INVITE_ONLY', false),
];
