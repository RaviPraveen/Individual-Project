<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;

/**
 * Thin, deliberately dumb wrapper around ActivityLog::record() — exists so
 * every call site reads the same way ("record what happened, in plain
 * English, tied to who did it") rather than each controller building the
 * fillable array by hand.
 */
class ActivityLogger
{
    public function log(?int $userId, string $action, string $description, ?Model $subject = null): ActivityLog
    {
        return ActivityLog::record($userId, $action, $description, $subject);
    }
}
