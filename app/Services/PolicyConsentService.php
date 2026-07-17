<?php

namespace App\Services;

use App\Models\PolicyConsent;
use Illuminate\Http\Request;
use InvalidArgumentException;

class PolicyConsentService
{
    /**
     * Ghi lại việc người dùng đồng ý với một hoặc nhiều chính sách.
     *
     * Phiên bản được đọc từ config tại đúng thời điểm này, không phải do phía
     * client gửi lên — nếu để client quyết định version thì bản ghi chấp thuận
     * chẳng còn là bằng chứng nữa.
     *
     * @param  string[]  $policyKeys  vd: ['terms', 'privacy']
     */
    public function record(
        array $policyKeys,
        string $context,
        Request $request,
        ?int $userId = null,
        ?string $subjectId = null,
    ): void {
        foreach ($policyKeys as $key) {
            PolicyConsent::create([
                'user_id'        => $userId ?? $request->user()?->id,
                'policy_key'     => $key,
                'policy_version' => $this->currentVersion($key),
                'context'        => $context,
                'subject_id'     => $subjectId,
                'consented_at'   => now(),
                'ip'             => $request->ip(),
                'user_agent'     => substr((string) $request->userAgent(), 0, 512),
            ]);
        }
    }

    /**
     * Phiên bản đang có hiệu lực của một chính sách.
     *
     * Ném lỗi thay vì trả về mặc định khi không tìm thấy: một key sai chính tả sẽ
     * lặng lẽ ghi hàng loạt bản ghi chấp thuận vô giá trị, và không ai phát hiện
     * cho tới lúc cần dùng tới chúng.
     */
    public function currentVersion(string $policyKey): string
    {
        foreach (config('policies.pages') as $page) {
            if ($page['key'] === $policyKey) {
                return $page['version'];
            }
        }

        throw new InvalidArgumentException("Không có chính sách '{$policyKey}' trong config/policies.php");
    }
}
