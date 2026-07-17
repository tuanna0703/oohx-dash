<?php

namespace Tests\Feature;

use App\Models\BookingLine;
use App\Models\Campaign;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\Owner;
use App\Models\OwnerReview;
use App\Models\Screen;
use App\Models\Site;
use App\Models\User;
use App\Services\OwnerReviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Đánh giá media owner sau booking (review mục 6).
 *
 * Hồ sơ đăng ký với Bộ Công Thương đã khai hai tiện ích "Đánh giá nhà cung cấp"
 * và "Đánh giá dịch vụ quảng cáo" nhưng trong code không có gì cả. Đây là phần
 * bù lại, và các test dưới đây canh hai thứ khiến điểm số có nghĩa: chỉ người đã
 * dùng dịch vụ mới được đánh giá, và mỗi campaign chỉ một lần cho mỗi owner.
 */
class OwnerReviewTest extends TestCase
{
    use RefreshDatabase;

    private Owner $owner;
    private Organization $org;
    private User $user;
    private Campaign $campaign;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = Owner::factory()->create(['status' => 'active', 'name' => 'Kim Ngân ADV']);
        $this->org  = $this->makeOrg('Agency X');
        $this->user = $this->makeMemberOf($this->org);

        $this->campaign = $this->makeCampaign(Campaign::STATUS_COMPLETED, 'completed');
    }

    private function makeOrg(string $name): Organization
    {
        return Organization::create([
            'name' => $name, 'slug' => Str::slug($name) . '-' . uniqid(), 'type' => 'agency',
        ]);
    }

    /** Middleware `buyer` đòi có bản ghi thành viên, không chỉ current_organization_id. */
    private function makeMemberOf(Organization $org): User
    {
        $user = User::factory()->create(['current_organization_id' => $org->id]);
        OrganizationUser::create([
            'organization_id' => $org->id,
            'user_id'         => $user->id,
            'role'            => OrganizationUser::ROLE_ADMIN,
        ]);

        return $user;
    }

    private function makeCampaign(string $campaignStatus, string $lineStatus): Campaign
    {
        $site   = Site::factory()->create(['owner_id' => $this->owner->id]);
        $screen = Screen::factory()->create(['owner_id' => $this->owner->id, 'site_id' => $site->id]);

        $campaign = Campaign::create([
            'organization_id' => $this->org->id,
            'created_by'      => $this->user->id,
            'code'            => 'CPN-' . uniqid(),
            'name'            => 'Chiến dịch thử',
            'start_date'      => now()->subMonth(),
            'end_date'        => now()->subDay(),
            'status'          => $campaignStatus,
        ]);

        BookingLine::create([
            'campaign_id' => $campaign->id,
            'screen_id'   => $screen->id,
            'owner_id'    => $this->owner->id,
            'start_date'  => now()->subMonth(),
            'end_date'    => now()->subDay(),
            'status'      => $lineStatus,
        ]);

        return $campaign;
    }

    // ── Ai được đánh giá ─────────────────────────────────────────────────────

    public function test_campaign_da_chay_xong_thi_duoc_danh_gia_owner(): void
    {
        $owners = app(OwnerReviewService::class)->reviewableOwners($this->campaign);

        $this->assertCount(1, $owners);
        $this->assertSame($this->owner->id, $owners->first()->id);
    }

    public function test_campaign_con_la_nhap_thi_khong_duoc_danh_gia(): void
    {
        $draft = $this->makeCampaign(Campaign::STATUS_DRAFT, 'pending');

        $this->assertCount(
            0,
            app(OwnerReviewService::class)->reviewableOwners($draft),
            'đánh giá một dịch vụ chưa được cung cấp thì không dựa trên trải nghiệm nào'
        );
    }

    public function test_khong_danh_gia_duoc_owner_khong_co_trong_campaign(): void
    {
        $stranger = Owner::factory()->create(['status' => 'active']);

        $this->expectException(\RuntimeException::class);

        app(OwnerReviewService::class)->submit($this->campaign, $stranger, $this->user, 5, null);
    }

    public function test_moi_campaign_chi_danh_gia_moi_owner_mot_lan(): void
    {
        $svc = app(OwnerReviewService::class);
        $svc->submit($this->campaign, $this->owner, $this->user, 4, 'Tốt');

        $this->assertCount(
            0,
            $svc->reviewableOwners($this->campaign->fresh()),
            'đã đánh giá rồi thì không hiện form nữa'
        );

        // Ràng buộc thật nằm ở tầng DB — hai request song song lọt qua kiểm tra
        // ở tầng ứng dụng thì vẫn phải bị chặn.
        $this->expectException(\Illuminate\Database\QueryException::class);
        OwnerReview::create([
            'campaign_id'     => $this->campaign->id,
            'owner_id'        => $this->owner->id,
            'organization_id' => $this->org->id,
            'user_id'         => $this->user->id,
            'rating'          => 1,
        ]);
    }

    // ── Kiểm duyệt trước khi hiện ────────────────────────────────────────────

    public function test_danh_gia_moi_chua_hien_ngay(): void
    {
        $review = app(OwnerReviewService::class)
            ->submit($this->campaign, $this->owner, $this->user, 5, 'Rất tốt');

        $this->assertSame(OwnerReview::STATUS_PENDING, $review->status);
        $this->assertSame(0, OwnerReview::published()->count());
    }

    public function test_diem_trung_binh_chi_tinh_danh_gia_da_duyet(): void
    {
        OwnerReview::factory()->published()->create([
            'campaign_id' => $this->campaign->id, 'owner_id' => $this->owner->id,
            'organization_id' => $this->org->id, 'user_id' => $this->user->id, 'rating' => 4,
        ]);
        // Đánh giá 1 sao chưa duyệt — không được kéo điểm xuống khi chưa ai đọc.
        $other = $this->makeCampaign(Campaign::STATUS_COMPLETED, 'completed');
        OwnerReview::factory()->create([
            'campaign_id' => $other->id, 'owner_id' => $this->owner->id,
            'organization_id' => $this->org->id, 'user_id' => $this->user->id, 'rating' => 1,
        ]);

        $summary = app(OwnerReviewService::class)->summaryFor($this->owner->id);

        $this->assertSame(4.0, $summary['avg']);
        $this->assertSame(1, $summary['count']);
    }

    public function test_owner_chua_co_danh_gia_thi_khong_co_diem(): void
    {
        $summary = app(OwnerReviewService::class)->summaryFor($this->owner->id);

        $this->assertNull($summary['avg'], 'không có đánh giá thì không được bịa ra điểm 0');
        $this->assertSame(0, $summary['count']);
    }

    // ── Qua HTTP ─────────────────────────────────────────────────────────────

    public function test_gui_danh_gia_qua_form(): void
    {
        $response = $this->actingAs($this->user)->post(
            'http://' . config('domains.frontpage', 'oohx.net') . '/my/campaigns/' . $this->campaign->id . '/reviews',
            ['owner_id' => $this->owner->id, 'rating' => 5, 'comment' => 'Đúng hẹn, hỗ trợ tốt.']
        );

        $response->assertRedirect();
        $this->assertDatabaseHas('owner_reviews', [
            'owner_id' => $this->owner->id,
            'rating'   => 5,
            'status'   => OwnerReview::STATUS_PENDING,
        ]);
    }

    public function test_khong_danh_gia_duoc_campaign_cua_to_chuc_khac(): void
    {
        $intruder = $this->makeMemberOf($this->makeOrg('Agency Y'));

        $this->actingAs($intruder)->post(
            'http://' . config('domains.frontpage', 'oohx.net') . '/my/campaigns/' . $this->campaign->id . '/reviews',
            ['owner_id' => $this->owner->id, 'rating' => 1]
        )->assertForbidden();
    }

    public function test_so_sao_ngoai_khoang_1_5_bi_tu_choi(): void
    {
        $this->actingAs($this->user)->post(
            'http://' . config('domains.frontpage', 'oohx.net') . '/my/campaigns/' . $this->campaign->id . '/reviews',
            ['owner_id' => $this->owner->id, 'rating' => 9]
        )->assertSessionHasErrors('rating');
    }
}
