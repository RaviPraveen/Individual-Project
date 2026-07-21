<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ActivityLog extends Model
{
    protected $fillable = [
        'user_id',
        'action',
        'description',
        'subject_type',
        'subject_id',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Convenience factory used by ActivityLogger — kept on the model too
     * so a call site can log directly without pulling in the service if
     * it only needs the bare minimum.
     */
    public static function record(?int $userId, string $action, string $description, $subject = null): self
    {
        return static::create([
            'user_id' => $userId,
            'action' => $action,
            'description' => $description,
            'subject_type' => $subject ? $subject::class : null,
            'subject_id' => $subject?->id,
        ]);
    }
}
