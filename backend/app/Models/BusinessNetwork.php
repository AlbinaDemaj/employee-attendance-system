<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Një IP ose rang IP-je nga i cili lejohet check-in-i. */
class BusinessNetwork extends Model
{
    protected $fillable = [
        'business_id',
        'label',
        'ip_range',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }
}
