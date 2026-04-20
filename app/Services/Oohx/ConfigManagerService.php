<?php

namespace App\Services\Oohx;

use App\Models\Oohx\Config\AuditLog;
use App\Models\Oohx\Config\BaseCityTraffic;
use App\Models\Oohx\Config\DeliveryDefault;
use App\Models\Oohx\Config\FormulaVersion;
use App\Models\Oohx\Config\RoadClassMultiplier;
use App\Models\Oohx\Config\ZoneFactor;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Service tập trung mọi mutation lên config.* (write surface duy nhất cho UI).
 *
 * Đảm bảo:
 *   - Atomic transaction trên oohx_control connection (1 update + 1 audit row trong cùng transaction)
 *   - Audit log mỗi action với actor + before/after values
 *   - Validate range Laravel-side khớp DB CHECK constraints (form layer cũng validate, đây là last line)
 *   - Activate version sử dụng partial unique index để đảm bảo max 1 active
 *
 * Xem PHASE-2A-HANDOFF.md §6 để hiểu fallback semantics khi Python không thấy active version.
 */
class ConfigManagerService
{
    private const CONNECTION = 'oohx_control';

    /** Mapping group key (UI) → [Model class, PK column, value column, CHECK range] */
    private const GROUP_MAP = [
        'base_city_traffic' => [BaseCityTraffic::class,     'city',       'baseline_passby', 0,    1_000_000],
        'road_class'        => [RoadClassMultiplier::class, 'road_class', 'multiplier',      0.01, 5],
        'zone'              => [ZoneFactor::class,          'zone_type',  'factor',          0.01, 2],
        'delivery_default'  => [DeliveryDefault::class,     'key',        'value',           0,    100],
    ];

    /**
     * Update 1 coefficient atomically + audit.
     *
     * @throws \InvalidArgumentException nếu group/key không hợp lệ hoặc value out of range
     */
    public function updateCoefficient(string $group, string $key, float $newValue, ?string $note = null): void
    {
        if (! isset(self::GROUP_MAP[$group])) {
            throw new \InvalidArgumentException("Unknown config group: {$group}");
        }
        [$modelClass, $pkColumn, $valueColumn, $min, $max] = self::GROUP_MAP[$group];

        if ($newValue < $min || $newValue > $max) {
            throw new \InvalidArgumentException(
                "Value {$newValue} for {$group}.{$key} out of range [{$min}, {$max}]"
            );
        }

        $actor = $this->resolveActor();

        DB::connection(self::CONNECTION)->transaction(function () use (
            $modelClass, $pkColumn, $valueColumn, $key, $newValue, $note, $actor, $group
        ) {
            $row = $modelClass::where($pkColumn, $key)->first();
            $oldValue = $row?->{$valueColumn};

            $modelClass::updateOrCreate(
                [$pkColumn => $key],
                [
                    $valueColumn => $newValue,
                    'note'       => $note,
                    'updated_by' => $actor,
                    'updated_at' => now(),
                ],
            );

            AuditLog::create([
                'actor'      => $actor,
                'action'     => "update_{$group}",
                'target'     => "{$pkColumn}={$key}",
                'old_value'  => ['value' => $oldValue],
                'new_value'  => ['value' => $newValue],
                'note'       => $note,
                'created_at' => now(),
            ]);
        });
    }

    /**
     * Snapshot toàn bộ config.* hiện tại vào 1 formula_versions row.
     * KHÔNG activate — caller decide riêng (UI flow: publish first, review snapshot, then activate).
     *
     * @throws \Illuminate\Database\QueryException nếu tag trùng (UNIQUE constraint)
     */
    public function publishVersion(string $tag, ?string $description = null): FormulaVersion
    {
        $actor = $this->resolveActor();

        return DB::connection(self::CONNECTION)->transaction(function () use ($tag, $description, $actor) {
            $snapshot = $this->buildSnapshot();

            $version = FormulaVersion::create([
                'tag'         => $tag,
                'description' => $description,
                'snapshot'    => $snapshot,
                'is_active'   => false,
                'created_by'  => $actor,
                'created_at'  => now(),
            ]);

            AuditLog::create([
                'actor'      => $actor,
                'action'     => 'publish_version',
                'target'     => "tag={$tag}",
                'new_value'  => ['id' => $version->id, 'description' => $description],
                'created_at' => now(),
            ]);

            return $version;
        });
    }

    /**
     * Activate 1 version → atomic deactivate version cũ.
     * Partial unique index `idx_formula_versions_one_active` đảm bảo invariant.
     */
    public function activateVersion(string $tag): FormulaVersion
    {
        $actor = $this->resolveActor();

        return DB::connection(self::CONNECTION)->transaction(function () use ($tag, $actor) {
            $target = FormulaVersion::where('tag', $tag)->firstOrFail();

            if ($target->is_active) {
                return $target; // already active, no-op
            }

            FormulaVersion::where('is_active', true)->update(['is_active' => false]);
            $target->update(['is_active' => true, 'activated_at' => now()]);

            AuditLog::create([
                'actor'      => $actor,
                'action'     => 'activate_version',
                'target'     => "tag={$tag}",
                'new_value'  => ['id' => $target->id],
                'created_at' => now(),
            ]);

            return $target->fresh();
        });
    }

    /**
     * So sánh 2 version snapshot, return list của coefficient khác biệt.
     * Mỗi diff entry: {group, key, before, after, delta}.
     */
    public function diffVersions(string $tagA, string $tagB): array
    {
        $a = FormulaVersion::where('tag', $tagA)->firstOrFail()->snapshot;
        $b = FormulaVersion::where('tag', $tagB)->firstOrFail()->snapshot;

        $diff = [];
        foreach ($a as $group => $values) {
            foreach ((array) $values as $key => $val) {
                $other = $b[$group][$key] ?? null;
                if ($other === null || (float) $other === (float) $val) continue;
                $diff[] = [
                    'group'  => $group,
                    'key'    => $key,
                    'before' => (float) $val,
                    'after'  => (float) $other,
                    'delta'  => round($other - $val, 4),
                ];
            }
        }
        return $diff;
    }

    /**
     * Snapshot helper — xếp các bảng config thành dict 4 nhóm cho FormulaVersion.snapshot.
     */
    private function buildSnapshot(): array
    {
        return [
            'base_city_traffic'      => BaseCityTraffic::all()->mapWithKeys(fn ($r) => [$r->city => $r->baseline_passby])->all(),
            'road_class_multipliers' => RoadClassMultiplier::all()->mapWithKeys(fn ($r) => [$r->road_class => $r->multiplier])->all(),
            'zone_factors'           => ZoneFactor::all()->mapWithKeys(fn ($r) => [$r->zone_type => $r->factor])->all(),
            'delivery_defaults'      => DeliveryDefault::all()->mapWithKeys(fn ($r) => [$r->key => $r->value])->all(),
        ];
    }

    /**
     * Convention actor: email user nếu có, fallback `web:<id>`, cuối cùng "system".
     */
    private function resolveActor(): string
    {
        $u = Auth::user();
        if (! $u) return 'system';
        return $u->email ?? "web:{$u->id}";
    }

    // ── Read helpers (không write — dùng cho UI dashboard) ────────────────

    /**
     * Active version hiện tại (NULL nếu chưa publish/activate).
     * Banner UI dùng để hiện warning khi Python đang fallback tier 2/3.
     */
    public function activeVersion(): ?FormulaVersion
    {
        return FormulaVersion::where('is_active', true)->first();
    }

    /**
     * 4 nhóm coefficient hiện tại từ tables (không qua snapshot).
     * Dùng cho dashboard "Edit live" view.
     */
    public function listCoefficients(): array
    {
        return [
            'base_city_traffic' => BaseCityTraffic::orderBy('city')->get(),
            'road_class'        => RoadClassMultiplier::orderBy('road_class')->get(),
            'zone'              => ZoneFactor::orderBy('zone_type')->get(),
            'delivery_default'  => DeliveryDefault::orderBy('key')->get(),
        ];
    }
}
