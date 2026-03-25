<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScreenSpec extends Model
{
    use HasFactory;

    protected $table = 'screen_specs';
    protected $fillable = [
        'screen_id','width_px','height_px','resolution_preset','width_cm','width_unit','height_cm','height_unit',
        'facing_direction','photo_url',
        'allow_image','allow_video','allow_html','allow_zip','allow_vast',
        'allowed_languages',
    ];
    protected $casts = [
        'allow_image'=>'boolean','allow_video'=>'boolean',
        'allow_html'=>'boolean','allow_zip'=>'boolean','allow_vast'=>'boolean',
        'allowed_languages'=>'array',
        'width_cm'=>'decimal:1','height_cm'=>'decimal:1',
    ];
    public function screen(): BelongsTo { return $this->belongsTo(Screen::class); }
    public function getOrientationAttribute(): string {
        if ($this->width_px > $this->height_px) return 'landscape';
        if ($this->width_px < $this->height_px) return 'portrait';
        return 'square';
    }
    public function getAspectRatioAttribute(): string {
        if (!$this->width_px || !$this->height_px) return 'unknown';
        $gcd = $this->gcd($this->width_px, $this->height_px);
        return ($this->width_px/$gcd).':'.($this->height_px/$gcd);
    }
    private function gcd(int $a, int $b): int { return $b===0 ? $a : $this->gcd($b,$a%$b); }
}
