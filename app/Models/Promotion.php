<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Promotion extends Model
{
    public const STATUS_SCHEDULED = 'scheduled';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_PAUSED = 'paused';

    public const STATUS_EXPIRED = 'expired';

    public const PRIORITIES = ['high', 'normal', 'low'];

    public const TARGET_SCREENS = ['customer_display', 'dashboard_banner', 'both'];

    protected $fillable = [
        'title',
        'product_id',
        'description',
        'current_price',
        'offer_price',
        'discount_percentage',
        'poster_path',
        'poster_source',
        'product_photo_path',
        'ai_generations',
        'pending_poster_path',
        'pending_poster_used_ai',
        'start_date',
        'end_date',
        'display_duration',
        'priority',
        'status',
        'is_featured',
        'target_screen',
        'display_order',
        'display_count',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'current_price' => 'decimal:2',
            'offer_price' => 'decimal:2',
            'discount_percentage' => 'decimal:2',
            'ai_generations' => 'array',
            'start_date' => 'datetime',
            'end_date' => 'datetime',
            'is_featured' => 'boolean',
            'pending_poster_used_ai' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Determine the scheduled status without the manual pause setting.
    public function dateDerivedStatus(): string
    {
        $now = now();

        if ($now->lt($this->start_date)) {
            return self::STATUS_SCHEDULED;
        }

        if ($now->gt($this->end_date)) {
            return self::STATUS_EXPIRED;
        }

        return self::STATUS_ACTIVE;
    }

    // Keep a promotion paused until an admin resumes it.
    public function effectiveStatus(): string
    {
        return $this->status === self::STATUS_PAUSED
            ? self::STATUS_PAUSED
            : $this->dateDerivedStatus();
    }

    // Update due statuses when promotions are loaded; leave paused ones unchanged.
    public static function syncDueStatuses(): void
    {
        $now = now();

        static::where('status', self::STATUS_SCHEDULED)
            ->where('start_date', '<=', $now)
            ->where('end_date', '>=', $now)
            ->update(['status' => self::STATUS_ACTIVE]);

        static::whereIn('status', [self::STATUS_SCHEDULED, self::STATUS_ACTIVE])
            ->where('end_date', '<', $now)
            ->update(['status' => self::STATUS_EXPIRED]);
    }

    public function pause(): void
    {
        $this->update(['status' => self::STATUS_PAUSED]);
    }

    // Check canActivate() before resuming so expired promotions stay inactive.
    public function activate(): void
    {
        $this->update(['status' => $this->dateDerivedStatus()]);
    }

    public function canActivate(): bool
    {
        return $this->dateDerivedStatus() !== self::STATUS_EXPIRED;
    }

    public function scopeVisibleOnDisplay(Builder $query, string $screen = 'customer_display'): Builder
    {
        $now = now();

        return $query->where('status', self::STATUS_ACTIVE)
            ->where('start_date', '<=', $now)
            ->where('end_date', '>=', $now)
            ->whereIn('target_screen', [$screen, 'both']);
    }

    public function refreshDiscountPercentage(): void
    {
        $this->discount_percentage = $this->current_price > 0
            ? round((($this->current_price - $this->offer_price) / $this->current_price) * 100, 2)
            : 0;
    }

    public function posterDetails(): array
    {
        return [
            'product_id' => (int) $this->product_id,
            'title' => (string) $this->title,
            'description' => (string) $this->description,
            'current_price' => (string) $this->current_price,
            'offer_price' => (string) $this->offer_price,
            'product_photo_path' => $this->product_photo_path,
        ];
    }
}
