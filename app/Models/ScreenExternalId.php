<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScreenExternalId extends Model
{
    protected $table = 'screen_external_ids';
    protected $fillable = [
        'screen_id','platform','external_id','external_uuid',
        'sync_status','sync_error','last_synced_at',
    ];
    protected $casts = ['last_synced_at'=>'datetime'];
    public function screen(): BelongsTo { return $this->belongsTo(Screen::class); }
}
