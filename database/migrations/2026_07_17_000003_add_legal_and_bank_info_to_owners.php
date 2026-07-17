<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hồ sơ pháp lý và tài khoản nhận tiền của media owner.
 *
 * Hai yêu cầu khác nhau, cùng một bảng:
 *
 * 1. Review mục 8 + 10 — sàn phải lưu trữ thông tin đối tác và xác thực trước khi
 *    cho cung cấp dịch vụ. Trước đây bảng `owners` chỉ có `name` (tên hiển thị),
 *    email và điện thoại; không có tên pháp lý, mã số thuế, người đại diện, cũng
 *    không có chỗ nào đính kèm ĐKKD.
 *
 * 2. Hồ sơ đăng ký với Bộ Công Thương khai "thanh toán trực tiếp giữa khách hàng
 *    và nhà cung cấp; OOHX hỗ trợ ghi nhận và đối soát". Muốn đúng như khai thì
 *    người mua phải chuyển thẳng cho media owner, nên phải biết tài khoản của họ.
 *    Trước đây trang thanh toán hiển thị một số tài khoản giả cứng trong Blade.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('owners', function (Blueprint $table) {
            // ── Hồ sơ pháp lý ──
            $table->string('legal_name')->nullable()->after('name');
            $table->string('tax_code', 20)->nullable()->after('legal_name');
            $table->date('tax_code_issued_on')->nullable()->after('tax_code');
            $table->string('tax_code_issued_by')->nullable()->after('tax_code_issued_on');
            $table->string('legal_representative')->nullable()->after('tax_code_issued_by');

            // Giấy ĐKKD. Lưu trên disk riêng, không phải disk public: đây là giấy
            // tờ pháp lý của đối tác, không phải ảnh bìa.
            $table->string('business_license_path')->nullable()->after('legal_representative');

            $table->timestamp('verified_at')->nullable()->after('verified');
            $table->foreignId('verified_by_user_id')->nullable()->after('verified_at')
                ->constrained('users')->nullOnDelete();

            // ── Tài khoản nhận tiền ──
            $table->string('bank_name')->nullable();
            $table->string('bank_account_number', 40)->nullable();
            $table->string('bank_account_name')->nullable();
            $table->string('bank_branch')->nullable();

            $table->index('tax_code');
        });
    }

    public function down(): void
    {
        Schema::table('owners', function (Blueprint $table) {
            $table->dropConstrainedForeignId('verified_by_user_id');
            $table->dropIndex(['tax_code']);
            $table->dropColumn([
                'legal_name', 'tax_code', 'tax_code_issued_on', 'tax_code_issued_by',
                'legal_representative', 'business_license_path', 'verified_at',
                'bank_name', 'bank_account_number', 'bank_account_name', 'bank_branch',
            ]);
        });
    }
};
