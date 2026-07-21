<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;

/**
 * Thin wrapper around ActivityLog::record() that defaults the actor to the
 * currently authenticated user, so call sites don't each have to thread
 * $request->user()->id through.
 */
class ActivityLogger
{
    public function log(string $action, string $description, ?Model $subject = null, ?int $userId = null): ActivityLog
    {
        return ActivityLog::record($userId ?? auth()->id(), $action, $description, $subject);
    }
}
