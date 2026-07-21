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
        'ai_generations',
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

    /**
     * The status a promotion SHOULD have right now, based purely on its
     * dates — ignoring any stored 'paused' override. Used both to display
     * an accurate badge without waiting for the next sync, and by
     * syncDueStatuses()/activate() to decide what "resuming" should mean.
     */
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

    /**
     * What should actually be shown/used right now: a manual pause always
     * wins over the date-derived status (an admin turning a promotion off
     * must not have it silently reappear because its window is still open).
     */
    public function effectiveStatus(): string
    {
        return $this->status === self::STATUS_PAUSED
            ? self::STATUS_PAUSED
            : $this->dateDerivedStatus();
    }

    /**
     * Bulk-flips scheduled→active and (scheduled|active)→expired directly in
     * SQL. Called at the top of any admin/customer-display read path so
     * status stays correct without depending on a working cron scheduler in
     * dev — paused promotions are deliberately left alone.
     */
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

    /**
     * Manual "ON" toggle: resume respecting the schedule rather than forcing
     * active — a promotion whose window hasn't started yet goes back to
     * scheduled, and one whose window has already closed refuses to
     * reactivate at all (the caller should check canActivate() first and
     * surface the "expired, update the end date" message).
     */
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
}
