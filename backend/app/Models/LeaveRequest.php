<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class LeaveRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_id',
        'user_id',
        'type',
        'start_date',
        'end_date',
        'description',
        'certificate_path',
        'status',
        'decided_by',
        'decided_at',
        'manager_note',
    ];

    protected $appends = ['certificate_url', 'has_certificate'];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'decided_at' => 'datetime',
        ];
    }

    public const TYPES = ['sick_leave', 'annual_leave', 'day_off', 'business_trip', 'unpaid'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function getHasCertificateAttribute(): bool
    {
        return ! empty($this->certificate_path);
    }

    public function getCertificateUrlAttribute(): ?string
    {
        return $this->certificate_path
            ? Storage::disk('public')->url($this->certificate_path)
            : null;
    }

    /** Number of calendar days the leave covers. */
    public function dayCount(): int
    {
        return (int) $this->start_date->diffInDays($this->end_date) + 1;
    }
}
