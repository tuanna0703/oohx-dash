<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Một lần người dùng đồng ý với một bản chính sách cụ thể.
 */
class PolicyConsent extends Model
{
    use HasUlids;

    public const CONTEXT_REGISTER = 'register';
    public const CONTEXT_BOOKING  = 'booking';
    public const CONTEXT_PAYMENT  = 'payment';

    protected $fillable = [
        'user_id', 'policy_key', 'policy_version',
        'context', 'subject_id', 'consented_at', 'ip', 'user_agent',
    ];

    protected $casts = [
        'consented_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
