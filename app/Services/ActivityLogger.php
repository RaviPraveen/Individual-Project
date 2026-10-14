<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;

// Use the signed-in user when no user ID is supplied.
class ActivityLogger
{
    public function log(string $action, string $description, ?Model $subject = null, ?int $userId = null): ActivityLog
    {
        return ActivityLog::record($userId ?? auth()->id(), $action, $description, $subject);
    }
}
