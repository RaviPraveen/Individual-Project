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
        Paginator::useBootstrapFive();

        // Feeds the notification bell in layouts.navigation, which is shared
        // by both the admin and cashier shells — scoped to admins only since
        // every current alert type (low stock, pending PO, near-expiry) is
        // an admin/inventory concern.
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
