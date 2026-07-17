<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Đánh giá media owner sau khi booking (review mục 6).
 *
 * Hồ sơ đăng ký với Bộ Công Thương đã khai hai tiện ích "Đánh giá nhà cung cấp"
 * và "Đánh giá dịch vụ quảng cáo" — nhưng trong code không hề có bảng, model hay
 * cột tổng hợp nào. Đây là phần bù lại khoảng cách đó.
 *
 * Ràng buộc unique(campaign_id, owner_id): mỗi campaign chỉ được đánh giá mỗi
 * owner một lần. Đặt ở tầng DB chứ không chỉ ở validate — hai request gửi song
 * song sẽ lọt qua kiểm tra ở tầng ứng dụng.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('owner_reviews', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->ulid('campaign_id');
            $table->ulid('owner_id');
            $table->ulid('organization_id');
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            $table->unsignedTinyInteger('rating');   // 1..5
            $table->text('comment')->nullable();

            // Kiểm duyệt trước khi hiện: nội dung do người mua viết, có thể nhắc
            // tên bên thứ ba hoặc là lời lẽ không phù hợp.
            $table->enum('status', ['pending', 'published', 'rejected'])->default('pending');
            $table->text('moderation_note')->nullable();
            $table->timestamp('published_at')->nullable();

            $table->timestamps();

            $table->foreign('campaign_id')->references('id')->on('campaigns')->cascadeOnDelete();
            $table->foreign('owner_id')->references('id')->on('owners')->cascadeOnDelete();
            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();

            $table->unique(['campaign_id', 'owner_id']);
            $table->index(['owner_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('owner_reviews');
    }
};
