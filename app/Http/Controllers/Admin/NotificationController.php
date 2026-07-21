<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\RedirectResponse;

class NotificationController extends Controller
{
    public function markRead(Notification $notification): RedirectResponse
    {
        $notification->update(['is_read' => true]);

        return back();
    }

    public function markAllRead(): RedirectResponse
    {
        Notification::where('is_read', false)->update(['is_read' => true]);

        return back();
    }
}
