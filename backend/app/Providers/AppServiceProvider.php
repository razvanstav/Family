<?php

namespace App\Providers;

use App\Platform\Contracts\PrivateMedia;
use App\Platform\Media\ClamAvScanner;
use App\Platform\Media\LocalPrivateMedia;
use App\Platform\Media\Scanner;
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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
