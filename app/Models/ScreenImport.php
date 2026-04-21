<?php

namespace App\Models;

use App\Traits\HasOwnerScope;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Screen import job — tracks state across upload → mapping → preview → import.
 *
 * Owned per-owner (HasOwnerScope) + uploaded_by user for audit trail.
 *
 * Status machine:
 *   uploaded   — file saved, headers parsed, awaiting AI mapping
 *   mapping    — AI mapping proposed, user is reviewing/editing
 *   previewed  — dry-run validation complete, awaiting import confirmation
 *   importing  — queue job running (Phase 2)
 *   done       — all rows processed
 *   failed     — terminal failure
 *   cancelled  — user aborted (Phase 2)
 */
class ScreenImport extends Model
{
    use HasUuids, HasOwnerScope;

    protected $fillable = [
        'owner_id', 'uploaded_by',
        'original_filename', 'file_path',
        'status', 'upsert_mode',
        'total_rows', 'processed_count', 'success_count', 'failed_count',
        'headers', 'sample_rows',
        'ai_mapping', 'user_mapping', 'ai_comment_history',
        'preview_data', 'validation_errors', 'error_summary',
        'error_report_path',
        'started_at', 'finished_at',
    ];

    protected $casts = [
        'headers'             => 'array',
        'sample_rows'         => 'array',
        'ai_mapping'          => 'array',
        'user_mapping'        => 'array',
        'ai_comment_history'  => 'array',
        'preview_data'        => 'array',
        'validation_errors'   => 'array',
        'total_rows'          => 'integer',
        'processed_count'     => 'integer',
        'success_count'       => 'integer',
        'failed_count'        => 'integer',
        'started_at'          => 'datetime',
        'finished_at'         => 'datetime',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(Owner::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function getIsTerminalAttribute(): bool
    {
        return in_array($this->status, ['done', 'failed', 'cancelled'], true);
    }

    public function getIsActiveAttribute(): bool
    {
        return in_array($this->status, ['importing'], true);
    }

    public function getProgressPercentAttribute(): int
    {
        if (! $this->total_rows) return 0;
        return (int) round(($this->processed_count / $this->total_rows) * 100);
    }

    public function getEffectiveMappingAttribute(): array
    {
        return $this->user_mapping ?? $this->ai_mapping ?? [];
    }
}
