<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_id',
        'user_id',
        'work_date',
        'status',
        'expected_start_time',
        'checkin_time',
        'checkout_time',
        'late_minutes',
        'checkin_ip',
        'checkout_ip',
        'reason_category',
        'reason_note',
        'excused',
        'reported_at',
        'leave_request_id',
    ];

    protected function casts(): array
    {
        return [
            'work_date' => 'date',
            'checkin_time' => 'datetime',
            'checkout_time' => 'datetime',
            'reported_at' => 'datetime',
            'excused' => 'boolean',
            'late_minutes' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function leaveRequest(): BelongsTo
    {
        return $this->belongsTo(LeaveRequest::class);
    }

    /** Statuses that mean the person is legitimately not at work. */
    public const LEAVE_STATUSES = ['sick_leave', 'annual_leave', 'day_off', 'business_trip'];

    public const ALL_STATUSES = [
        'present',
        'late',
        'absent',
        'left_early',
        'sick_leave',
        'annual_leave',
        'day_off',
        'business_trip',
    ];

    public function isOnLeave(): bool
    {
        return in_array($this->status, self::LEAVE_STATUSES, true);
    }
}
