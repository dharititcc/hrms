<?php

namespace App\Providers;

use App\Enums\Ability;
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
        //
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

        // Ability gates for non-model checks, e.g. Gate::allows('export').
        //
        // Deliberately no Gate::before bypass for manage-all: it would skip the
        // workspace-ownership check inside every policy, letting one workspace
        // owner reach another workspace's records. manage-all means "every
        // ability within my own workspace" — policies still verify tenancy.
        foreach (Ability::cases() as $ability) {
            Gate::define($ability->value, fn ($user) => $user->hasAbility($ability));
        }
    }
}
