<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScreenImpressionMultiplier extends Model
{
    protected $table = 'screen_impression_multipliers';
    protected $fillable = ['screen_id','day_of_week','hour_of_day','multiplier'];
    protected $casts = ['multiplier'=>'decimal:2'];
    public function screen(): BelongsTo { return $this->belongsTo(Screen::class); }
    public static function parseHivestackString(string $str): array {
        $values = explode(',', $str);
        $result = [];
        foreach ($values as $i => $val) {
            $result[] = [
                'day_of_week' => (int)floor($i/24),
                'hour_of_day' => $i%24,
                'multiplier'  => is_numeric(trim($val)) ? (float)trim($val) : 1.0,
            ];
        }
        return $result;
    }
}
