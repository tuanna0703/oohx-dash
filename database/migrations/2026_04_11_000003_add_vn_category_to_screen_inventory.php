<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add vn_category_id column
        Schema::table('screen_inventory', function (Blueprint $table) {
            $table->unsignedTinyInteger('vn_category_id')->nullable()->after('venue_type');
            $table->foreign('vn_category_id')->references('id')->on('venue_categories')->nullOnDelete();
            $table->index('vn_category_id');
        });

        // 2. Migrate existing data: map venue_type → vn_category_id
        // Phase 1: Match via venue_types.string_value → vn_category_id
        DB::statement('
            UPDATE screen_inventory si
            JOIN venue_types vt ON si.venue_type = vt.string_value
            SET si.vn_category_id = vt.vn_category_id
            WHERE vt.vn_category_id IS NOT NULL
              AND si.vn_category_id IS NULL
        ');

        // Phase 2: Match Hivestack format via venue_types.category (case-insensitive prefix)
        $hivestackMapping = [
            'retail'        => 1,  // Bán lẻ
            'leisure'       => 11, // Giải trí
            'entertainment' => 11,
            'transit'       => 3,  // Giao thông
            'outdoor'       => 4,  // Ngoài trời
            'office'        => 5,  // Văn phòng
            'residential'   => 6,  // Khu dân cư
            'dining'        => 7,  // Ẩm thực
            'health'        => 8,  // Y tế
            'education'     => 9,  // Giáo dục
            'hospitality'   => 10, // Khách sạn
            'recreation'    => 12, // Thể thao
        ];

        $unmapped = DB::table('screen_inventory')
            ->whereNull('vn_category_id')
            ->whereNotNull('venue_type')
            ->where('venue_type', '!=', '')
            ->get(['id', 'venue_type']);

        foreach ($unmapped as $row) {
            $prefix = strtolower(trim(explode(':', $row->venue_type)[0]));

            // Special: "Malls" → Mall (2)
            if (str_contains($prefix, 'mall')) {
                DB::table('screen_inventory')->where('id', $row->id)->update(['vn_category_id' => 2]);
                continue;
            }

            foreach ($hivestackMapping as $key => $catId) {
                if (str_starts_with($prefix, $key)) {
                    DB::table('screen_inventory')->where('id', $row->id)->update(['vn_category_id' => $catId]);
                    break;
                }
            }
        }
    }

    public function down(): void
    {
        Schema::table('screen_inventory', function (Blueprint $table) {
            $table->dropForeign(['vn_category_id']);
            $table->dropIndex(['vn_category_id']);
            $table->dropColumn('vn_category_id');
        });
    }
};
