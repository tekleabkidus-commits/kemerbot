<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConversionEvent extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['occurred_at' => 'datetime', 'value' => 'decimal:2'];
    }
}
