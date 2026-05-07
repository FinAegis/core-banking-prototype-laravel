<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use App\Actions\Fortify\UpdateUserProfileInformation;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::updateUserProfileInformationUsing(UpdateUserProfileInformation::class);
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);

        // Replace Jetstream's email+password login view with our Privy
        // email-OTP flow when the feature flag is on. The matching POST
        // routes live in routes/web.php under the login.privy.* names.
        // Evaluate the flag inside the closure so tests can flip the config
        // after boot — and so a future env reload picks it up without a
        // process restart.
        Fortify::loginView(fn () => view(
            (bool) config('privy.web_login_enabled', false) ? 'auth.privy-login' : 'auth.login'
        ));
        Fortify::registerView(fn () => view(
            (bool) config('privy.web_login_enabled', false) ? 'auth.privy-login' : 'auth.register'
        ));

        RateLimiter::for(
            'login',
            function (Request $request) {
                $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())) . '|' . $request->ip());

                return Limit::perMinute(5)->by($throttleKey);
            }
        );

        RateLimiter::for(
            'two-factor',
            function (Request $request) {
                return Limit::perMinute(5)->by($request->session()->get('login.id'));
            }
        );
    }
}
