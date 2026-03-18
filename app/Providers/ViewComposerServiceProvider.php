<?php

namespace App\Providers;

use App\View\Composers\HeaderNotificationsComposer;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class ViewComposerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        View::composer('layouts.app', HeaderNotificationsComposer::class);
    }
}
