<?php

namespace App\Providers;

use App\Actions\Jetstream\AddTeamMember;
use App\Actions\Jetstream\CreateTeam;
use App\Actions\Jetstream\DeleteTeam;
use App\Actions\Jetstream\DeleteUser;
use App\Actions\Jetstream\InviteTeamMember;
use App\Actions\Jetstream\RemoveTeamMember;
use App\Actions\Jetstream\UpdateTeamName;
use App\Livewire\Profile\DeleteUserForm;
use Illuminate\Support\ServiceProvider;
use Laravel\Jetstream\Jetstream;
use Livewire\Livewire;

class JetstreamServiceProvider extends ServiceProvider
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
        $this->configurePermissions();

        Jetstream::createTeamsUsing(CreateTeam::class);
        Jetstream::updateTeamNamesUsing(UpdateTeamName::class);
        Jetstream::addTeamMembersUsing(AddTeamMember::class);
        Jetstream::inviteTeamMembersUsing(InviteTeamMember::class);
        Jetstream::removeTeamMembersUsing(RemoveTeamMember::class);
        Jetstream::deleteTeamsUsing(DeleteTeam::class);
        Jetstream::deleteUsersUsing(DeleteUser::class);

        // Replaces Jetstream's form, which only knows password confirmation.
        // Registered after every provider has booted, so it wins over the
        // registration in Jetstream's own service provider.
        $this->app->booted(function (): void {
            Livewire::component('profile.delete-user-form', DeleteUserForm::class);
        });
    }

    /**
     * Configure the roles and permissions that are available within the application.
     */
    protected function configurePermissions(): void
    {
        Jetstream::defaultApiTokenPermissions(['read']);

        Jetstream::role(
            'admin',
            'Administrator',
            [
                'create',
                'read',
                'update',
                'delete',
            ]
        )->description('Administrator users can perform any action.');

        Jetstream::role(
            'editor',
            'Editor',
            [
                'read',
                'create',
                'update',
            ]
        )->description('Editor users have the ability to read, create, and update.');
    }
}
