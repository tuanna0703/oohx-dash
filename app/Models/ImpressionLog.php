<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImpressionLog extends Model
{
    public $timestamps = false;
    protected $table = 'impression_logs';
    protected $fillable = [
        'id','screen_id','owner_id','campaign_id','creative_id',
        'played_at','duration_sec','multiplier_applied','imp_count',
        'deal_type','cpm_charged','revenue_gross','revenue_owner',
        'proof_url','source','created_at',
    ];
    protected $casts = [
        'played_at'=>'datetime','imp_count'=>'decimal:2',
        'multiplier_applied'=>'decimal:2','cpm_charged'=>'decimal:4',
        'revenue_gross'=>'decimal:4','revenue_owner'=>'decimal:4',
    ];
    public function screen(): BelongsTo { return $this->belongsTo(Screen::class); }
    public function owner(): BelongsTo { return $this->belongsTo(Owner::class); }
}
