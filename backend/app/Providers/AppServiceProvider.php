<?php

namespace App\Providers;

use App\Modules\Calendar\Application\CalendarReminders;
use App\Modules\Kitchen\Application\RecipeCatalog;
use App\Modules\Kitchen\Contracts\Recipes;
use App\Platform\Contracts\PrivateMedia;
use App\Platform\Families\MembershipRevoked;
use App\Platform\Media\ClamAvScanner;
use App\Platform\Media\LocalPrivateMedia;
use App\Platform\Media\Scanner;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(Scanner::class, ClamAvScanner::class);
        $this->app->bind(PrivateMedia::class, LocalPrivateMedia::class);
        $this->app->bind(Recipes::class, RecipeCatalog::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::listen(MembershipRevoked::class, function (MembershipRevoked $event): void {
            $this->app->make(CalendarReminders::class)->cancelMembership($event->familyId, $event->membershipId);
            $this->app->make(RecipeCatalog::class)->revokeMembership($event->familyId, $event->membershipId);
        });
    }
}
