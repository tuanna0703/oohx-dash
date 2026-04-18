<?php

namespace App\Console\Commands;

use App\Models\Screen;
use Illuminate\Console\Command;

/**
 * Áp default Phase 1 Inventory Intelligence cho screens dựa trên venue category.
 *
 * Default behavior: chỉ áp cho screens chưa có data (skip nếu placement_zone hoặc
 * daily_footfall hoặc bất kỳ JSON nhóm nào đã được fill trước đó).
 *
 * Dùng --overwrite để cố tình ghi đè data cũ (cẩn thận).
 */
class SeedInsightsDefaults extends Command
{
    protected $signature = 'oohx:seed-insights-defaults
                            {--dry-run : Preview, không ghi DB}
                            {--overwrite : Ghi đè cả khi screen đã có data}
                            {--category= : Chỉ áp cho 1 venue category slug (mall, roadside, ...)}';

    protected $description = 'Áp template Phase 1 Inventory Intelligence theo venue category — minh bạch là estimate.';

    private const METHODOLOGY = 'Ước lượng theo industry benchmark Việt Nam (placeholder MVP). Cần validate bằng dữ liệu đo thực tế tại site.';

    /**
     * Template per category slug. Key = venue_categories.slug.
     * % phải tổng ≈ 100 trong từng nhóm (gender, age, time-of-day).
     */
    private function templates(): array
    {
        return [
            'mall' => [
                'placement_zone'   => 'food_court',
                'daily_footfall'   => 50000,
                'monthly_reach'    => 900000,
                'audience_profile' => [
                    'male_pct' => 45, 'female_pct' => 55,
                    'age_18_24_pct' => 25, 'age_25_34_pct' => 40, 'age_35_44_pct' => 25, 'age_45_plus_pct' => 10,
                    'source_note' => 'Industry benchmark TTTM tại VN (Vincom/AEON/Lotte).',
                ],
                'time_performance' => [
                    'peak_hour_start' => '18:00', 'peak_hour_end' => '21:00', 'best_day' => 'sat',
                    'morning_pct' => 15, 'afternoon_pct' => 35, 'evening_pct' => 50,
                ],
                'nearby_context' => ['highlights' => 'Trung tâm thương mại — flow khách ổn định, dwell time dài.'],
            ],
            'retail' => [
                'placement_zone'   => 'entrance',
                'daily_footfall'   => 15000,
                'monthly_reach'    => 280000,
                'audience_profile' => [
                    'male_pct' => 40, 'female_pct' => 60,
                    'age_18_24_pct' => 20, 'age_25_34_pct' => 38, 'age_35_44_pct' => 27, 'age_45_plus_pct' => 15,
                    'source_note' => 'Industry benchmark chuỗi bán lẻ VN.',
                ],
                'time_performance' => [
                    'peak_hour_start' => '17:00', 'peak_hour_end' => '20:00', 'best_day' => 'sat',
                    'morning_pct' => 20, 'afternoon_pct' => 35, 'evening_pct' => 45,
                ],
                'nearby_context' => ['highlights' => 'Cửa hàng bán lẻ — purchase intent cao tại điểm bán.'],
            ],
            'transit' => [
                'placement_zone'   => 'lobby',
                'daily_footfall'   => 80000,
                'monthly_reach'    => 1500000,
                'audience_profile' => [
                    'male_pct' => 50, 'female_pct' => 50,
                    'age_18_24_pct' => 22, 'age_25_34_pct' => 38, 'age_35_44_pct' => 25, 'age_45_plus_pct' => 15,
                    'source_note' => 'Số liệu vận hành điểm giao thông công cộng VN.',
                ],
                'time_performance' => [
                    'peak_hour_start' => '07:00', 'peak_hour_end' => '09:00', 'best_day' => 'mon',
                    'morning_pct' => 40, 'afternoon_pct' => 25, 'evening_pct' => 35,
                ],
                'nearby_context' => ['highlights' => 'Điểm giao thông — frequency cao, exposure đa dạng audience.'],
            ],
            'roadside' => [
                'placement_zone'   => 'facade',
                'daily_footfall'   => 25000,
                'monthly_reach'    => 450000,
                'audience_profile' => [
                    'male_pct' => 55, 'female_pct' => 45,
                    'age_18_24_pct' => 18, 'age_25_34_pct' => 35, 'age_35_44_pct' => 28, 'age_45_plus_pct' => 19,
                    'source_note' => 'Ước lượng lưu lượng tuyến đường nội đô VN.',
                ],
                'time_performance' => [
                    'peak_hour_start' => '17:00', 'peak_hour_end' => '20:00', 'best_day' => 'fri',
                    'morning_pct' => 30, 'afternoon_pct' => 30, 'evening_pct' => 40,
                ],
                'nearby_context' => ['highlights' => 'Billboard ngoài trời — reach cao, brand awareness mạnh.'],
            ],
            'office' => [
                'placement_zone'   => 'lobby',
                'daily_footfall'   => 5000,
                'monthly_reach'    => 90000,
                'audience_profile' => [
                    'male_pct' => 50, 'female_pct' => 50,
                    'age_18_24_pct' => 10, 'age_25_34_pct' => 45, 'age_35_44_pct' => 35, 'age_45_plus_pct' => 10,
                    'source_note' => 'Khảo sát toà văn phòng hạng A/B tại HN/HCM.',
                ],
                'time_performance' => [
                    'peak_hour_start' => '08:00', 'peak_hour_end' => '10:00', 'best_day' => 'wed',
                    'morning_pct' => 45, 'afternoon_pct' => 30, 'evening_pct' => 25,
                ],
                'nearby_context' => ['highlights' => 'Toà văn phòng — audience white-collar, income cao.'],
            ],
            'residential' => [
                'placement_zone'   => 'lobby',
                'daily_footfall'   => 3000,
                'monthly_reach'    => 50000,
                'audience_profile' => [
                    'male_pct' => 48, 'female_pct' => 52,
                    'age_18_24_pct' => 15, 'age_25_34_pct' => 30, 'age_35_44_pct' => 30, 'age_45_plus_pct' => 25,
                    'source_note' => 'Ước lượng mật độ cư dân khu căn hộ VN.',
                ],
                'time_performance' => [
                    'peak_hour_start' => '18:00', 'peak_hour_end' => '21:00', 'best_day' => 'sun',
                    'morning_pct' => 30, 'afternoon_pct' => 25, 'evening_pct' => 45,
                ],
                'nearby_context' => ['highlights' => 'Khu dân cư — frequency hằng ngày, dwell time dài.'],
            ],
            'food-beverage' => [
                'placement_zone'   => 'lobby',
                'daily_footfall'   => 8000,
                'monthly_reach'    => 140000,
                'audience_profile' => [
                    'male_pct' => 45, 'female_pct' => 55,
                    'age_18_24_pct' => 30, 'age_25_34_pct' => 40, 'age_35_44_pct' => 20, 'age_45_plus_pct' => 10,
                    'source_note' => 'Industry benchmark chuỗi F&B đô thị VN.',
                ],
                'time_performance' => [
                    'peak_hour_start' => '11:00', 'peak_hour_end' => '14:00', 'best_day' => 'sat',
                    'morning_pct' => 20, 'afternoon_pct' => 40, 'evening_pct' => 40,
                ],
                'nearby_context' => ['highlights' => 'Nhà hàng / quán cafe — dwell time cao, audience trẻ tuổi.'],
            ],
            'healthcare' => [
                'placement_zone'   => 'lobby',
                'daily_footfall'   => 4000,
                'monthly_reach'    => 70000,
                'audience_profile' => [
                    'male_pct' => 45, 'female_pct' => 55,
                    'age_18_24_pct' => 12, 'age_25_34_pct' => 28, 'age_35_44_pct' => 30, 'age_45_plus_pct' => 30,
                    'source_note' => 'Khảo sát bệnh viện / phòng khám đô thị VN.',
                ],
                'time_performance' => [
                    'peak_hour_start' => '09:00', 'peak_hour_end' => '11:00', 'best_day' => 'mon',
                    'morning_pct' => 50, 'afternoon_pct' => 35, 'evening_pct' => 15,
                ],
                'nearby_context' => ['highlights' => 'Cơ sở y tế — captive audience, dwell time rất cao.'],
            ],
            'education' => [
                'placement_zone'   => 'entrance',
                'daily_footfall'   => 6000,
                'monthly_reach'    => 100000,
                'audience_profile' => [
                    'male_pct' => 50, 'female_pct' => 50,
                    'age_18_24_pct' => 60, 'age_25_34_pct' => 25, 'age_35_44_pct' => 10, 'age_45_plus_pct' => 5,
                    'source_note' => 'Khảo sát trường ĐH / TTHN tại VN.',
                ],
                'time_performance' => [
                    'peak_hour_start' => '08:00', 'peak_hour_end' => '11:00', 'best_day' => 'tue',
                    'morning_pct' => 50, 'afternoon_pct' => 35, 'evening_pct' => 15,
                ],
                'nearby_context' => ['highlights' => 'Trường học — audience Gen Z, brand-loyal khi tiếp cận sớm.'],
            ],
            'hospitality' => [
                'placement_zone'   => 'lobby',
                'daily_footfall'   => 2500,
                'monthly_reach'    => 40000,
                'audience_profile' => [
                    'male_pct' => 55, 'female_pct' => 45,
                    'age_18_24_pct' => 10, 'age_25_34_pct' => 30, 'age_35_44_pct' => 35, 'age_45_plus_pct' => 25,
                    'source_note' => 'Khảo sát khách sạn 4-5 sao tại VN.',
                ],
                'time_performance' => [
                    'peak_hour_start' => '17:00', 'peak_hour_end' => '20:00', 'best_day' => 'fri',
                    'morning_pct' => 25, 'afternoon_pct' => 30, 'evening_pct' => 45,
                ],
                'nearby_context' => ['highlights' => 'Khách sạn — audience income cao, mixed VN + foreign.'],
            ],
            'entertainment' => [
                'placement_zone'   => 'lobby',
                'daily_footfall'   => 12000,
                'monthly_reach'    => 200000,
                'audience_profile' => [
                    'male_pct' => 50, 'female_pct' => 50,
                    'age_18_24_pct' => 35, 'age_25_34_pct' => 40, 'age_35_44_pct' => 18, 'age_45_plus_pct' => 7,
                    'source_note' => 'Industry benchmark rạp chiếu phim / KTV VN.',
                ],
                'time_performance' => [
                    'peak_hour_start' => '19:00', 'peak_hour_end' => '22:00', 'best_day' => 'sat',
                    'morning_pct' => 10, 'afternoon_pct' => 35, 'evening_pct' => 55,
                ],
                'nearby_context' => ['highlights' => 'Khu giải trí — peak cuối tuần / tối, mood thư giãn cao.'],
            ],
            'sports-fitness' => [
                'placement_zone'   => 'entrance',
                'daily_footfall'   => 2000,
                'monthly_reach'    => 35000,
                'audience_profile' => [
                    'male_pct' => 55, 'female_pct' => 45,
                    'age_18_24_pct' => 25, 'age_25_34_pct' => 45, 'age_35_44_pct' => 22, 'age_45_plus_pct' => 8,
                    'source_note' => 'Khảo sát chuỗi gym / fitness center đô thị VN.',
                ],
                'time_performance' => [
                    'peak_hour_start' => '18:00', 'peak_hour_end' => '21:00', 'best_day' => 'mon',
                    'morning_pct' => 30, 'afternoon_pct' => 20, 'evening_pct' => 50,
                ],
                'nearby_context' => ['highlights' => 'Trung tâm thể thao — audience health-conscious, repeat exposure cao.'],
            ],
        ];
    }

    public function handle(): int
    {
        $isDry      = $this->option('dry-run');
        $overwrite  = $this->option('overwrite');
        $catFilter  = $this->option('category');
        $templates  = $this->templates();

        if ($catFilter && ! isset($templates[$catFilter])) {
            $this->error("Unknown category slug: {$catFilter}. Available: " . implode(', ', array_keys($templates)));
            return self::FAILURE;
        }

        $query = Screen::query()
            ->where('active', true)
            ->whereHas('inventory', fn ($q) => $q->whereNotNull('vn_category_id'))
            ->with('inventory.vnCategory:id,slug');

        $screens = $query->get();
        $byCategory = $screens->groupBy(fn ($s) => $s->inventory?->vnCategory?->slug);

        $stats = ['updated' => 0, 'skipped_has_data' => 0, 'skipped_no_template' => 0, 'skipped_filter' => 0];

        foreach ($byCategory as $slug => $group) {
            if (! $slug) {
                $stats['skipped_no_template'] += $group->count();
                continue;
            }
            if ($catFilter && $slug !== $catFilter) {
                $stats['skipped_filter'] += $group->count();
                continue;
            }
            if (! isset($templates[$slug])) {
                $stats['skipped_no_template'] += $group->count();
                continue;
            }

            $tpl = $templates[$slug];
            $this->line("<fg=cyan>[{$slug}]</> {$group->count()} screens");

            foreach ($group as $screen) {
                if (! $overwrite && $this->hasAnyData($screen)) {
                    $stats['skipped_has_data']++;
                    continue;
                }

                if (! $isDry) {
                    $screen->fill([
                        'placement_zone'           => $tpl['placement_zone'],
                        'daily_footfall'           => $tpl['daily_footfall'],
                        'monthly_reach'            => $tpl['monthly_reach'],
                        'audience_profile'         => $tpl['audience_profile'],
                        'time_performance'         => $tpl['time_performance'],
                        'nearby_context'           => $tpl['nearby_context'],
                        'traffic_methodology_note' => self::METHODOLOGY,
                    ])->saveQuietly();
                }
                $stats['updated']++;
            }
        }

        $this->newLine();
        $this->table(
            ['Metric', 'Count'],
            [
                ['Updated' . ($isDry ? ' (dry-run)' : ''), $stats['updated']],
                ['Skipped — đã có data',                   $stats['skipped_has_data']],
                ['Skipped — không có template',            $stats['skipped_no_template']],
                ['Skipped — filtered out',                 $stats['skipped_filter']],
                ['TOTAL active screens scanned',           $screens->count()],
            ]
        );

        if ($isDry) {
            $this->warn('DRY RUN — không có gì ghi vào DB. Bỏ --dry-run để chạy thật.');
        } elseif ($stats['updated'] > 0) {
            $this->info("✔ Đã update {$stats['updated']} screens với placeholder benchmark.");
            $this->line('  Methodology note: ' . self::METHODOLOGY);
        }

        return self::SUCCESS;
    }

    private function hasAnyData(Screen $s): bool
    {
        return (bool) (
            $s->placement_zone
            || $s->daily_footfall
            || $s->monthly_reach
            || ! empty($s->audience_profile)
            || ! empty($s->time_performance)
            || ! empty($s->nearby_context)
        );
    }
}
