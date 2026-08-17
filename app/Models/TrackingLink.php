<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\TrackingLinkFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * `code` is immutable once traffic exists (spec §5.6) — guarded in the admin
 * layer. Renaming `name` never rewrites attribution.
 */
class TrackingLink extends Model
{
    /** @use HasFactory<TrackingLinkFactory> */
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'description',
        'is_active',
        'clicks_count',
        'joins_count',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'clicks_count' => 'integer',
            'joins_count' => 'integer',
        ];
    }

    public function conversionRate(): ?float
    {
        if ($this->clicks_count === 0) {
            return null;
        }

        return round($this->joins_count / $this->clicks_count * 100, 1);
    }
}
