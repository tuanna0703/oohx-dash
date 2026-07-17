<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Một khoản thanh toán thuộc về một media owner cụ thể.
 *
 * Sàn không thu hộ (theo đúng hồ sơ đã khai): người mua chuyển thẳng cho từng
 * media owner. Một campaign có thể gồm màn hình của nhiều owner, nên nó sinh ra
 * nhiều lần chuyển khoản chứ không phải một. Giữ `payments` ở mức một khoản/một
 * campaign trong khi màn hình hiển thị tài khoản của từng owner sẽ tạo ra một
 * bảng đối soát nói dối: tiền đi tới nhiều nơi mà sổ chỉ ghi một dòng.
 *
 * Nullable vì các payment có trước thay đổi này không thuộc owner nào — chúng
 * được tạo dưới mô hình sàn thu hộ, và gán bừa một owner vào là bịa lại lịch sử.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->ulid('owner_id')->nullable()->after('organization_id');
            $table->foreign('owner_id')->references('id')->on('owners')->nullOnDelete();
            $table->index(['campaign_id', 'owner_id']);
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['owner_id']);
            $table->dropIndex(['campaign_id', 'owner_id']);
            $table->dropColumn('owner_id');
        });
    }
};
