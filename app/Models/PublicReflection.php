<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Phản ánh của tổ chức xã hội gửi tới sàn.
 *
 * @property string $organization_name
 * @property string $subject
 * @property string $content
 * @property string $status  pending|in_review|resolved|rejected
 */
class PublicReflection extends Model
{
    use HasFactory, HasUlids;

    public const STATUS_PENDING   = 'pending';
    public const STATUS_IN_REVIEW = 'in_review';
    public const STATUS_RESOLVED  = 'resolved';
    public const STATUS_REJECTED  = 'rejected';

    public const STATUS_LABELS = [
        self::STATUS_PENDING   => 'Chờ xử lý',
        self::STATUS_IN_REVIEW => 'Đang xem xét',
        self::STATUS_RESOLVED  => 'Đã xử lý',
        self::STATUS_REJECTED  => 'Không tiếp nhận',
    ];

    protected $fillable = [
        'code', 'organization_name', 'subject', 'content', 'received_at',
        'contact_name', 'contact_email', 'contact_phone', 'internal_notes',
        'status', 'resolution', 'resolved_at', 'handled_by_user_id',
        'published_at', 'submitted_ip',
    ];

    /**
     * Dữ liệu cá nhân của người gửi. Ẩn khỏi mọi lần serialize để một cú
     * `->toJson()` hay `@json($reflection)` trong Blade không vô tình đẩy email
     * và số điện thoại ra trang công khai.
     */
    protected $hidden = [
        'contact_name', 'contact_email', 'contact_phone',
        'internal_notes', 'submitted_ip',
    ];

    protected $casts = [
        'received_at'  => 'datetime',
        'resolved_at'  => 'datetime',
        'published_at' => 'datetime',
    ];

    public function handledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by_user_id');
    }

    /**
     * Các phản ánh được phép hiện trên trang danh sách công khai.
     *
     * Chỉ những gì admin đã chủ động cho đăng. Không suy ra từ `status` —
     * "đã xử lý" không đồng nghĩa với "được phép đăng nguyên văn".
     */
    public function scopePublished($query)
    {
        return $query->whereNotNull('published_at');
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }
}
