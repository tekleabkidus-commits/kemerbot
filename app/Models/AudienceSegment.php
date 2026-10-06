<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AudienceSegment extends Model
{
    protected $fillable = ['name', 'description', 'filter'];

    protected function casts(): array
    {
        return ['filter' => 'array'];
    }
}
