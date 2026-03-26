<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Thêm các cột còn thiếu vào bảng screen_inventory:
 *   - screen_count_override  : override số màn hình vật lý tại 1 location
 *   - frequency_cap          : advertiser frequency cap (giây)
 *   - category_frequency_cap : category frequency cap (giây)
 *   - strict_frequency_capping: bật/tắt strict freq cap
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('screen_inventory', function (Blueprint $table) {
            if (! Schema::hasColumn('screen_inventory', 'screen_count_override')) {
                $table->unsignedSmallInteger('screen_count_override')
                      ->nullable()
                      ->default(null)
                      ->after('share_of_voice_max_pct')
                      ->comment('Override số màn hình tại location — NULL = 1 (mặc định)');
            }
            if (! Schema::hasColumn('screen_inventory', 'frequency_cap')) {
                $table->unsignedInteger('frequency_cap')
                      ->default(0)
                      ->after('screen_count_override')
                      ->comment('Advertiser frequency cap tính bằng giây (0 = unlimited)');
            }
            if (! Schema::hasColumn('screen_inventory', 'category_frequency_cap')) {
                $table->unsignedInteger('category_frequency_cap')
                      ->default(0)
                      ->after('frequency_cap')
                      ->comment('Category frequency cap tính bằng giây (0 = unlimited)');
            }
            if (! Schema::hasColumn('screen_inventory', 'strict_frequency_capping')) {
                $table->boolean('strict_frequency_capping')
                      ->default(false)
                      ->after('category_frequency_cap')
                      ->comment('Bật strict frequency capping — từ chối ad nếu đạt cap');
            }
        });
    }

    public function down(): void
    {
        Schema::table('screen_inventory', function (Blueprint $table) {
            $table->dropColumn([
                'screen_count_override',
                'frequency_cap',
                'category_frequency_cap',
                'strict_frequency_capping',
            ]);
        });
    }
};
