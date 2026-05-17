<?php

namespace App\Models;

use App\Support\ChartDefaults;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Chart extends Model
{
    protected $fillable = [
        'user_id',
        'data',
    ];

    protected $casts = [
        'data' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function createDefault(?int $userId = null): self
    {
        return self::create([
            'user_id' => $userId,
            'data' => ChartDefaults::defaultChart(),
        ]);
    }
}
