<?php

namespace App\Providers;

use App\Support\PermissionRegistry;
use App\Services\Meetings\ManualMeetingLinkProvider;
use App\Services\Meetings\MeetingLinkProvider;
use App\Support\WorkspaceRecords;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Swap this binding for a Google-backed provider once OAuth credentials
        // are configured; nothing else needs to change.
        $this->app->bind(MeetingLinkProvider::class, ManualMeetingLinkProvider::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Polymorphic aliases. enforceMorphMap() means an unmapped class throws
        // rather than silently storing a FQCN, so clients can only ever name a
        // type from this whitelist. Includes User because the notifications
        // table is polymorphic on notifiable.
        Relation::enforceMorphMap(WorkspaceRecords::morphMap());

        // Registers every module.action as a gate, so controllers can call
        // $this->authorize('payroll.create') directly.
        //
        // Deliberately no Gate::before bypass for admins: it would skip the
        // workspace-ownership check inside every policy, letting one workspace
        // owner reach another workspace's records. Admin means "every
        // permission within my own workspace" — policies still verify tenancy.
        foreach (PermissionRegistry::all() as [$module, $action]) {
            Gate::define(
                "{$module->value}.{$action->value}",
                fn ($user) => $user->hasPermission($module, $action),
            );
        }
    }
}
