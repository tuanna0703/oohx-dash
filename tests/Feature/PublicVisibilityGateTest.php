<?php

namespace Tests\Feature;

use App\Models\Owner;
use App\Models\Product;
use App\Models\Screen;
use App\Models\Site;
use App\Services\FrontpageService;
use App\Services\ProductService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * Sàn chỉ được đăng tải công khai inventory của media owner đã duyệt.
 *
 * Yêu cầu review đăng ký sàn TMĐT (mục 9): "sàn cần kiểm soát đối tác có năng
 * lực cung ứng dịch vụ trước khi đăng tải công khai thông tin trên website".
 *
 * Trước fix: cổng chặn duy nhất là `Screen::where('active', true)` — không hề
 * đụng tới owners.status. Một media owner vừa được tạo (status='pending', là
 * giá trị mặc định) có màn hình hiện công khai ngay lập tức.
 *
 * Sau fix: `Screen::publiclyVisible()` / `Product::publiclyVisible()` bắt buộc
 * cả hai điều kiện — owner đã active VÀ bản ghi đang bật.
 */
class PublicVisibilityGateTest extends TestCase
{
    use RefreshDatabase;

    private function screenFor(Owner $owner): Screen
    {
        return Screen::factory()->create([
            'owner_id' => $owner->id,
            'site_id'  => Site::factory()->create(['owner_id' => $owner->id])->id,
        ]);
    }

    // ── Regression: owner chờ duyệt không được lộ ────────────────────────────

    public function test_screen_cua_owner_pending_khong_hien_cong_khai(): void
    {
        $pending = Owner::factory()->create(['status' => 'pending']);
        $screen  = $this->screenFor($pending);

        $this->assertTrue($screen->active, 'màn hình vẫn bật — chỉ owner là chưa duyệt');
        $this->assertFalse(
            Screen::publiclyVisible()->where('id', $screen->id)->exists(),
            'owner chưa được duyệt thì màn hình không được lộ ra ngoài'
        );
    }

    public function test_screen_cua_owner_suspended_khong_hien_cong_khai(): void
    {
        $suspended = Owner::factory()->create(['status' => 'suspended']);
        $screen    = $this->screenFor($suspended);

        $this->assertFalse(Screen::publiclyVisible()->where('id', $screen->id)->exists());
    }

    public function test_owner_bi_xoa_mem_thi_screen_khong_hien(): void
    {
        $owner  = Owner::factory()->create(['status' => 'active']);
        $screen = $this->screenFor($owner);
        $owner->delete();

        $this->assertFalse(Screen::publiclyVisible()->where('id', $screen->id)->exists());
    }

    // ── Mặt còn lại: owner đã duyệt vẫn phải hiện ────────────────────────────

    public function test_screen_cua_owner_active_van_hien_binh_thuong(): void
    {
        $owner  = Owner::factory()->create(['status' => 'active']);
        $screen = $this->screenFor($owner);

        $this->assertTrue(
            Screen::publiclyVisible()->where('id', $screen->id)->exists(),
            'cổng duyệt không được làm trống sàn của owner hợp lệ'
        );
    }

    public function test_owner_active_nhung_screen_tat_thi_khong_hien(): void
    {
        $owner  = Owner::factory()->create(['status' => 'active']);
        $screen = Screen::factory()->inactive()->create([
            'owner_id' => $owner->id,
            'site_id'  => Site::factory()->create(['owner_id' => $owner->id])->id,
        ]);

        $this->assertFalse(
            Screen::publiclyVisible()->where('id', $screen->id)->exists(),
            'cổng owner không được nuốt mất công tắc active của chính owner'
        );
    }

    // ── Các lối vào công khai đều phải đi qua cổng ────────────────────────────

    public function test_listing_khong_tra_ve_screen_cua_owner_pending(): void
    {
        $active  = Owner::factory()->create(['status' => 'active']);
        $pending = Owner::factory()->create(['status' => 'pending']);
        $shown   = $this->screenFor($active);
        $hidden  = $this->screenFor($pending);

        $ids = app(FrontpageService::class)
            ->getScreensPaginated(new Request())
            ->pluck('id');

        $this->assertContains($shown->id, $ids);
        $this->assertNotContains($hidden->id, $ids);
    }

    public function test_trang_chi_tiet_screen_cua_owner_pending_tra_ve_null(): void
    {
        $pending = Owner::factory()->create(['status' => 'pending']);
        $screen  = $this->screenFor($pending);

        $this->assertNull(app(FrontpageService::class)->getScreenDetail($screen->id));
    }

    public function test_hero_stats_khong_dem_screen_cua_owner_pending(): void
    {
        $active  = Owner::factory()->create(['status' => 'active']);
        $pending = Owner::factory()->create(['status' => 'pending']);
        $this->screenFor($active);
        $this->screenFor($pending);
        $this->screenFor($pending);

        $stats = app(FrontpageService::class)->getHeroStats();

        $this->assertSame(1, $stats['total_screens'], 'con số trên trang chủ phải khớp với thứ khách xem được');
        $this->assertSame(1, $stats['total_owners']);
    }

    public function test_product_cua_owner_pending_khong_hien_cong_khai(): void
    {
        $pending = Owner::factory()->create(['status' => 'pending']);
        $product = Product::create([
            'owner_id' => $pending->id,
            'name'     => 'Gói thử',
            'slug'     => 'goi-thu',
            'type'     => 'package',
            'category' => 'billboard',
            'status'   => 'active',
        ]);

        $this->assertFalse(Product::publiclyVisible()->where('id', $product->id)->exists());
        $this->assertNull(app(ProductService::class)->getProductBySlug('goi-thu'));
    }
}
