<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bằng chứng người dùng đã đồng ý với chính sách nào, bản nào, lúc nào.
 *
 * Một checkbox không có bản ghi thì không chứng minh được gì khi có tranh chấp:
 * chính sách sẽ được sửa nhiều lần, và "họ đã tick đồng ý" là vô nghĩa nếu không
 * nói được là đồng ý với BẢN NÀO. Vì vậy `policy_version` được đóng dấu vào từng
 * hàng, lấy từ config/policies.php tại thời điểm tick.
 *
 * `user_id` cho phép null: chấp thuận ở bước đăng ký xảy ra TRƯỚC khi user tồn
 * tại — hàng được ghi trong cùng transaction rồi gắn user_id ngay sau đó.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('policy_consents', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->string('policy_key', 40);        // terms | privacy | disputes
            $table->string('policy_version', 40);    // bản đang có hiệu lực lúc tick
            $table->string('context', 40);           // register | booking | payment
            $table->ulid('subject_id')->nullable();  // campaign id nếu là booking/payment

            $table->timestamp('consented_at');
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 512)->nullable();

            $table->timestamps();

            $table->index(['user_id', 'policy_key']);
            $table->index(['context', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('policy_consents');
    }
};
