<?php

namespace Tests\Feature;

use App\Models\PublicReflection;
use App\Services\PublicReflectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tiếp nhận và công bố phản ánh của tổ chức xã hội (review mục 3).
 *
 * Điểm dễ sai nhất của tính năng này không phải là form, mà là ranh giới giữa
 * phần công khai (tổ chức, nội dung, kết quả xử lý) và phần không được công khai
 * (email, điện thoại người gửi, ghi chú nội bộ). Phần lớn test dưới đây canh
 * đúng ranh giới đó.
 */
class PublicReflectionTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'organization_name' => 'Hội Bảo vệ người tiêu dùng TP. Hà Nội',
            'subject'           => 'Phản ánh về thông tin màn hình quảng cáo',
            'content'           => 'Nội dung phản ánh đủ dài để vượt qua ngưỡng tối thiểu hai mươi ký tự.',
            'contact_name'      => 'Nguyễn Văn A',
            'contact_email'     => 'nguoigui@example.org',
            'contact_phone'     => '0900000000',
        ], $overrides);
    }

    private function domain(): string
    {
        return config('domains.frontpage', 'oohx.net');
    }

    // ── Tiếp nhận ────────────────────────────────────────────────────────────

    public function test_gui_phan_anh_thi_duoc_ghi_nhan_va_tra_ma_tra_cuu(): void
    {
        $response = $this->post('http://' . $this->domain() . '/phan-anh-to-chuc-xa-hoi', $this->payload());

        $response->assertRedirect();
        $response->assertSessionHas('reflection_code');

        $this->assertDatabaseHas('public_reflections', [
            'organization_name' => 'Hội Bảo vệ người tiêu dùng TP. Hà Nội',
            'status'            => PublicReflection::STATUS_PENDING,
        ]);
    }

    public function test_phan_anh_moi_khong_tu_dong_len_trang_cong_khai(): void
    {
        $this->post('http://' . $this->domain() . '/phan-anh-to-chuc-xa-hoi', $this->payload());

        $reflection = PublicReflection::first();

        $this->assertNull(
            $reflection->published_at,
            'form công khai mà tự xuất bản là cửa ngỏ cho spam và nội dung bôi nhọ'
        );
        $this->assertSame(0, PublicReflection::published()->count());
    }

    public function test_thieu_email_lien_he_thi_khong_nhan(): void
    {
        $response = $this->post(
            'http://' . $this->domain() . '/phan-anh-to-chuc-xa-hoi',
            $this->payload(['contact_email' => ''])
        );

        $response->assertSessionHasErrors('contact_email');
        $this->assertSame(0, PublicReflection::count());
    }

    public function test_noi_dung_qua_ngan_thi_khong_nhan(): void
    {
        $response = $this->post(
            'http://' . $this->domain() . '/phan-anh-to-chuc-xa-hoi',
            $this->payload(['content' => 'ngắn'])
        );

        $response->assertSessionHasErrors('content');
    }

    public function test_bot_dien_vao_bay_spam_thi_bi_tu_choi(): void
    {
        $response = $this->post(
            'http://' . $this->domain() . '/phan-anh-to-chuc-xa-hoi',
            $this->payload(['website' => 'http://spam.example'])
        );

        $response->assertSessionHasErrors('website');
        $this->assertSame(0, PublicReflection::count());
    }

    // ── Công bố: ranh giới dữ liệu ───────────────────────────────────────────

    public function test_danh_sach_cong_khai_chi_hien_phan_anh_da_duoc_dang(): void
    {
        $hidden = app(PublicReflectionService::class)->record($this->payload(['subject' => 'Chưa duyệt đăng']));
        $shown  = app(PublicReflectionService::class)->record($this->payload(['subject' => 'Đã duyệt đăng']));
        $shown->update(['published_at' => now()]);

        $response = $this->get('http://' . $this->domain() . '/phan-anh-to-chuc-xa-hoi/danh-sach');

        $response->assertOk();
        $response->assertSee('Đã duyệt đăng');
        $response->assertDontSee('Chưa duyệt đăng');
    }

    public function test_trang_cong_khai_khong_lo_email_va_dien_thoai_nguoi_gui(): void
    {
        $reflection = app(PublicReflectionService::class)->record($this->payload());
        $reflection->update(['published_at' => now()]);

        $response = $this->get('http://' . $this->domain() . '/phan-anh-to-chuc-xa-hoi/danh-sach');

        $response->assertOk();
        $response->assertDontSee('nguoigui@example.org');
        $response->assertDontSee('0900000000');
        $response->assertDontSee('Nguyễn Văn A');
    }

    public function test_ghi_chu_noi_bo_khong_bao_gio_ra_trang_cong_khai(): void
    {
        $reflection = app(PublicReflectionService::class)->record($this->payload());
        $reflection->update([
            'published_at'   => now(),
            'internal_notes' => 'BÍ MẬT NỘI BỘ không được lộ',
            'resolution'     => 'Đã rà soát và điều chỉnh thông tin.',
        ]);

        $response = $this->get('http://' . $this->domain() . '/phan-anh-to-chuc-xa-hoi/danh-sach');

        $response->assertDontSee('BÍ MẬT NỘI BỘ không được lộ');
        $response->assertSee('Đã rà soát và điều chỉnh thông tin.');
    }

    public function test_serialize_model_khong_kem_du_lieu_ca_nhan(): void
    {
        $reflection = app(PublicReflectionService::class)->record($this->payload());

        $array = $reflection->toArray();

        $this->assertArrayNotHasKey('contact_email', $array);
        $this->assertArrayNotHasKey('contact_phone', $array);
        $this->assertArrayNotHasKey('contact_name', $array);
        $this->assertArrayNotHasKey('internal_notes', $array);
        $this->assertArrayNotHasKey('submitted_ip', $array);
        $this->assertArrayHasKey('organization_name', $array, 'phần công khai vẫn phải serialize được');
    }

    // ── Mã tra cứu ───────────────────────────────────────────────────────────

    public function test_ma_tra_cuu_tang_dan_va_khong_trung(): void
    {
        $svc = app(PublicReflectionService::class);

        $a = $svc->record($this->payload());
        $b = $svc->record($this->payload());

        $prefix = 'PA-' . now()->format('Ym') . '-';
        $this->assertSame($prefix . '0001', $a->code);
        $this->assertSame($prefix . '0002', $b->code);
    }
}
