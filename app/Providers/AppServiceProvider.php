<?php

namespace App\Providers;

use App\Models\Notification;
use App\Services\NotificationGenerator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
    }

    public function boot(): void
    {
        Paginator::useBootstrapFive();

        // Inventory alerts are shown only to admins in the shared navigation.
        View::composer('layouts.navigation', function (\Illuminate\View\View $view) {
            $user = Auth::user();

            if (! $user || ! $user->isAdmin()) {
                $view->with(['unreadNotifications' => collect(), 'unreadNotificationsCount' => 0]);

                return;
            }

            app(NotificationGenerator::class)->generate();

            $unread = Notification::where('is_read', false)->latest()->limit(8)->get();

            $view->with([
                'unreadNotifications' => $unread,
                'unreadNotificationsCount' => Notification::where('is_read', false)->count(),
            ]);
        });
    }
}
