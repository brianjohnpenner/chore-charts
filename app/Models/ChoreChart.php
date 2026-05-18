<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

#[Fillable(['public_id', 'email', 'title', 'data'])]
class ChoreChart extends Model
{
    protected function casts(): array
    {
        return [
            'data' => 'array',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public static function newPublicId(): string
    {
        do {
            $id = Str::lower(Str::random(16));
        } while (self::where('public_id', $id)->exists());

        return $id;
    }
}
