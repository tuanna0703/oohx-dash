<?php

namespace App\Models;

use App\Traits\HasOwnerScope;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Screen extends Model
{
    use HasFactory, HasUlids, HasOwnerScope, SoftDeletes;

    protected $fillable = [
        'site_id', 'owner_id', 'external_id', 'uuid',
        'unit_id', 'name', 'description', 'internal_notes',
        'site_external_id', 'network_code', 'location_district', 'location_district_code',
        'active', 'status',
        'player_type', 'player_version',
        'last_heartbeat_at', 'device_token',
    ];

    protected $casts = [
        'active'            => 'boolean',
        'last_heartbeat_at' => 'datetime',
    ];

    // Auto-generate UUID on create
    protected static function booted(): void
    {
        static::creating(function (Screen $screen) {
            if (empty($screen->uuid)) {
                $screen->uuid = (string) Str::uuid();
            }
        });
    }

    // ── Relationships ──────────────────────────────────────

    public function owner(): BelongsTo
    {
        return $this->belongsTo(Owner::class);
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function network(): BelongsTo
    {
        return $this->belongsTo(Network::class, 'network_code', 'code');
    }

    public function spec(): HasOne
    {
        return $this->hasOne(ScreenSpec::class);
    }

    public function inventory(): HasOne
    {
        return $this->hasOne(ScreenInventory::class);
    }

    public function multipliers(): HasMany
    {
        return $this->hasMany(ScreenImpressionMultiplier::class);
    }

    public function externalIds(): HasMany
    {
        return $this->hasMany(ScreenExternalId::class);
    }

    public function impressionLogs(): HasMany
    {
        return $this->hasMany(ImpressionLog::class);
    }

    // ── Scopes ─────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    public function scopeOnline($query)
    {
        return $query->where('status', 'online');
    }

    public function scopeProgrammatic($query)
    {
        return $query->whereHas('inventory', fn($q) =>
            $q->where('programmatic_enabled', true)
        );
    }

    public function scopeByVenueType($query, string $venueType)
    {
        return $query->whereHas('inventory', fn($q) =>
            $q->where('venue_type', $venueType)
        );
    }

    // ── Helpers ────────────────────────────────────────────

    /** Lấy multiplier cho giờ hiện tại */
    public function getCurrentMultiplier(): float
    {
        $now = now()->setTimezone($this->inventory?->timezone ?? 'Asia/Ho_Chi_Minh');
        $dow = $now->dayOfWeek === 0 ? 6 : $now->dayOfWeek - 1; // Laravel: 0=Sun, Hivestack: 0=Mon

        return (float) ($this->multipliers()
            ->where('day_of_week', $dow)
            ->where('hour_of_day', $now->hour)
            ->value('multiplier') ?? 1.0);
    }

    /** Kiểm tra screen có đang trong giờ hoạt động không */
    public function isOperating(): bool
    {
        $hours = $this->inventory?->operating_hours;
        if (empty($hours)) return true;

        $tz  = $this->inventory?->timezone ?? 'Asia/Ho_Chi_Minh';
        $now = now()->setTimezone($tz);
        $day = strtolower($now->format('D')); // mon, tue...

        $dayHours = $hours[$day] ?? null;
        if ($dayHours === 'closed' || empty($dayHours)) return false;

        $open  = $now->copy()->setTimeFromTimeString($dayHours['open']);
        $close = $now->copy()->setTimeFromTimeString($dayHours['close']);

        return $now->between($open, $close);
    }

    public function getIsOnlineAttribute(): bool
    {
        if (! $this->last_heartbeat_at) return false;
        return $this->last_heartbeat_at->diffInMinutes(now()) < 5;
    }
}
