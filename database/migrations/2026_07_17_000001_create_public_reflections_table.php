<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tiếp nhận phản ánh của tổ chức xã hội (yêu cầu review đăng ký sàn TMĐT, mục 3).
 *
 * Hai mặt của cùng một bảng, và ranh giới giữa chúng là điểm dễ sai nhất:
 *   - Trang /phan-anh-to-chuc-xa-hoi/danh-sach hiện CÔNG KHAI: tổ chức, nội dung,
 *     ngày tiếp nhận, trạng thái, kết quả xử lý.
 *   - KHÔNG BAO GIỜ công khai: email, số điện thoại người gửi, ghi chú nội bộ.
 *     Đó là dữ liệu cá nhân theo Nghị định 13/2023/NĐ-CP, thu để liên hệ lại chứ
 *     không phải để đăng lên.
 *
 * `published_at` là công tắc: một phản ánh chỉ lên danh sách công khai sau khi
 * admin đã đọc và cho phép. Mặc định null — không có gì tự động lộ ra.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('public_reflections', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('code', 20)->unique();       // PA-YYYYMM-XXXX, để người gửi tra cứu

            // ── Công khai được ──
            $table->string('organization_name');         // tổ chức xã hội gửi phản ánh
            $table->string('subject');
            $table->text('content');
            $table->timestamp('received_at');

            // ── Không công khai ──
            $table->string('contact_name')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('contact_phone', 30)->nullable();
            $table->text('internal_notes')->nullable();

            // ── Xử lý ──
            $table->enum('status', ['pending', 'in_review', 'resolved', 'rejected'])
                ->default('pending');
            $table->text('resolution')->nullable();      // kết quả xử lý — công khai cùng phản ánh
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('handled_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamp('published_at')->nullable();
            $table->string('submitted_ip', 45)->nullable();

            $table->timestamps();

            $table->index(['published_at', 'received_at']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('public_reflections');
    }
};
