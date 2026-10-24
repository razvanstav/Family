<?php

namespace App\Providers;

use App\Modules\Calendar\Application\BirthdayCalendar;
use App\Modules\Calendar\Application\CalendarOverview;
use App\Modules\Calendar\Application\CalendarReminders;
use App\Modules\Calendar\Application\CalendarSources;
use App\Modules\Calendar\Application\SourceCalendarItems;
use App\Modules\Calendar\Contracts\Birthdays;
use App\Modules\Calendar\Contracts\BirthdaySource;
use App\Modules\Calendar\Contracts\Overview;
use App\Modules\Calendar\Contracts\SourceItems;
use App\Modules\Documents\Application\DocumentAccess;
use App\Modules\Documents\Application\DocumentSearch;
use App\Modules\Documents\Contracts\DocumentLinks;
use App\Modules\Documents\Contracts\DocumentSummaries;
use App\Modules\Kitchen\Application\KitchenActivity;
use App\Modules\Kitchen\Application\KitchenActivityReader;
use App\Modules\Kitchen\Application\MealActivityReferences;
use App\Modules\Kitchen\Application\RecipeCatalog;
use App\Modules\Kitchen\Application\RecipeSearch;
use App\Modules\Kitchen\Contracts\Activity;
use App\Modules\Kitchen\Contracts\MealReferences;
use App\Modules\Kitchen\Contracts\Recipes;
use App\Modules\Memory\Application\MomentAccess;
use App\Modules\Memory\Application\MomentSearch;
use App\Modules\Memory\Application\RecentMoments;
use App\Modules\Memory\Application\TimelineProjection;
use App\Modules\Memory\Contracts\MomentSummaries;
use App\Modules\Pets\Application\PetDeadlineSource;
use App\Modules\Tasks\Application\TaskDeadlineSource;
use App\Modules\Vehicles\Application\VehicleDeadlineSource;
use App\Platform\Administration\BrevoTransport;
use App\Platform\Contracts\Outbox;
use App\Platform\Contracts\PrivateMedia;
use App\Platform\Events\DatabaseOutbox;
use App\Platform\Families\FamilyBirthdaySource;
use App\Platform\Families\MembershipRevoked;
use App\Platform\Media\ClamAvScanner;
use App\Platform\Media\LocalPrivateMedia;
use App\Platform\Media\Scanner;
use App\Platform\Search\FamilySearch;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(DocumentLinks::class, DocumentAccess::class);
        $this->app->bind(DocumentSummaries::class, DocumentAccess::class);
        $this->app->bind(MomentSummaries::class, RecentMoments::class);
        $this->app->bind(Overview::class, CalendarOverview::class);
        $this->app->bind(SourceItems::class, SourceCalendarItems::class);
        $this->app->bind(Birthdays::class, BirthdayCalendar::class);
        $this->app->bind(BirthdaySource::class, FamilyBirthdaySource::class);
        $this->app->bind(FamilySearch::class, fn ($app) => new FamilySearch([$app->make(DocumentSearch::class), $app->make(MomentSearch::class), $app->make(RecipeSearch::class)]));
        $this->app->bind(CalendarSources::class, fn ($app) => new CalendarSources([$app->make(VehicleDeadlineSource::class), $app->make(FamilyBirthdaySource::class), $app->make(TaskDeadlineSource::class), $app->make(PetDeadlineSource::class)]));
        $this->app->bind(Scanner::class, ClamAvScanner::class);
        $this->app->bind(PrivateMedia::class, LocalPrivateMedia::class);
        $this->app->bind(Recipes::class, RecipeCatalog::class);
        $this->app->bind(Activity::class, KitchenActivityReader::class);
        $this->app->bind(MealReferences::class, MealActivityReferences::class);
        $this->app->singleton(DatabaseOutbox::class, fn ($app) => new DatabaseOutbox([$app->make(KitchenActivity::class), $app->make(TimelineProjection::class)]));
        $this->app->bind(Outbox::class, fn ($app) => $app->make(DatabaseOutbox::class));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Mail::extend('amora', fn (): BrevoTransport => $this->app->make(BrevoTransport::class));
        RateLimiter::for('admin-email-test', fn (Request $request): Limit => Limit::perMinute(3)->by((string) $request->user()->id));
        Event::listen(MembershipRevoked::class, function (MembershipRevoked $event): void {
            $this->app->make(DocumentAccess::class)->revokeMembership($event->familyId, $event->membershipId);
            $this->app->make(CalendarReminders::class)->cancelMembership($event->familyId, $event->membershipId);
            $this->app->make(RecipeCatalog::class)->revokeMembership($event->familyId, $event->membershipId);
            $this->app->make(MomentAccess::class)->revokeMembership($event->familyId, $event->membershipId);
        });
    }
}
