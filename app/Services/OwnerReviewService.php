<?php

namespace App\Services;

use App\Models\Campaign;
use App\Models\Owner;
use App\Models\OwnerReview;
use App\Models\User;
use Illuminate\Support\Collection;
use RuntimeException;

class OwnerReviewService
{
    /**
     * Các media owner mà campaign này được phép đánh giá.
     *
     * Chỉ campaign đã chạy xong: đánh giá một dịch vụ chưa được cung cấp thì
     * không dựa trên trải nghiệm nào cả, và đó là cách nhanh nhất để điểm số trên
     * sàn trở nên vô nghĩa.
     *
     * @return Collection<int, Owner>
     */
    public function reviewableOwners(Campaign $campaign): Collection
    {
        if (! $this->campaignIsReviewable($campaign)) {
            return collect();
        }

        $ownerIds = $campaign->bookingLines()
            ->whereIn('status', ['active', 'completed'])
            ->pluck('owner_id')
            ->unique();

        if ($ownerIds->isEmpty()) {
            return collect();
        }

        $already = OwnerReview::where('campaign_id', $campaign->id)
            ->pluck('owner_id');

        return Owner::whereIn('id', $ownerIds->diff($already))->get();
    }

    public function campaignIsReviewable(Campaign $campaign): bool
    {
        return in_array($campaign->status, [Campaign::STATUS_COMPLETED, Campaign::STATUS_ACTIVE], true);
    }

    /**
     * Ghi nhận một đánh giá. Chưa hiện ngay — admin duyệt nội dung trước.
     */
    public function submit(Campaign $campaign, Owner $owner, User $user, int $rating, ?string $comment): OwnerReview
    {
        if (! $this->campaignIsReviewable($campaign)) {
            throw new RuntimeException('Chỉ đánh giá được sau khi campaign đã chạy.');
        }

        if (! $campaign->bookingLines()->where('owner_id', $owner->id)->whereIn('status', ['active', 'completed'])->exists()) {
            throw new RuntimeException('Campaign này không có màn hình nào của media owner đó.');
        }

        return OwnerReview::create([
            'campaign_id'     => $campaign->id,
            'owner_id'        => $owner->id,
            'organization_id' => $campaign->organization_id,
            'user_id'         => $user->id,
            'rating'          => $rating,
            'comment'         => $comment,
            'status'          => OwnerReview::STATUS_PENDING,
        ]);
    }

    /**
     * Điểm trung bình và số lượt đánh giá đã đăng của một owner.
     *
     * @return array{avg: float|null, count: int}
     */
    public function summaryFor(string $ownerId): array
    {
        $row = OwnerReview::published()
            ->where('owner_id', $ownerId)
            ->selectRaw('AVG(rating) as avg_rating, COUNT(*) as cnt')
            ->first();

        return [
            'avg'   => $row?->avg_rating !== null ? round((float) $row->avg_rating, 1) : null,
            'count' => (int) ($row?->cnt ?? 0),
        ];
    }

    /**
     * Các đánh giá đã đăng của một owner, kèm tên tổ chức đánh giá.
     */
    public function publishedFor(string $ownerId, int $limit = 10): Collection
    {
        return OwnerReview::published()
            ->where('owner_id', $ownerId)
            ->with('organization:id,name')
            ->orderByDesc('published_at')
            ->limit($limit)
            ->get();
    }
}
