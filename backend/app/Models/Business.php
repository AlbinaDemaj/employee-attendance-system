<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Një klient i produktit. Çdo përdorues (përveç super-adminit) i përket një
 * biznesi, dhe çdo e dhënë e prezencës filtrohet sipas tij.
 */
class Business extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'contact_email',
        'contact_phone',
        'address',
        'timezone',
        'default_start_time',
        'default_end_time',
        'working_days',
        'late_grace_minutes',
        'manager_alert_after_minutes',
        'auto_approve_sick_with_certificate',
        'require_network_for_checkin',
        'is_active',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'working_days' => 'array',
            'auto_approve_sick_with_certificate' => 'boolean',
            'require_network_for_checkin' => 'boolean',
            'is_active' => 'boolean',
            'late_grace_minutes' => 'integer',
            'manager_alert_after_minutes' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Business $business) {
            $business->slug = $business->slug ?: static::uniqueSlug($business->name);
            $business->working_days = $business->working_days ?: [1, 2, 3, 4, 5];

            // Vlerat default janë në databazë, por Eloquent nuk i mbush vetë në
            // objektin e sapokrijuar — kodi që e lexon menjëherë pas create()
            // do të merrte null.
            $business->default_start_time = $business->default_start_time ?: '08:00:00';
            $business->default_end_time = $business->default_end_time ?: '16:00:00';
        });
    }

    public static function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'biznes';
        $slug = $base;
        $i = 2;

        while (static::where('slug', $slug)->exists()) {
            $slug = $base . '-' . $i++;
        }

        return $slug;
    }

    // ------------------------------------------------------------ relacionet

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function employees(): HasMany
    {
        return $this->hasMany(User::class)->where('role', 'employee');
    }

    public function networks(): HasMany
    {
        return $this->hasMany(BusinessNetwork::class);
    }

    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }

    // -------------------------------------------------------------- rregullat

    /** [1..7] ku 1 = e hënë. */
    public function workingDays(): array
    {
        return $this->working_days ?: config('attendance.working_days');
    }

    public function lateGraceMinutes(): int
    {
        return (int) $this->late_grace_minutes;
    }

    public function alertAfterMinutes(): int
    {
        return (int) $this->manager_alert_after_minutes;
    }

    public function autoApprovesSick(): bool
    {
        return (bool) $this->auto_approve_sick_with_certificate;
    }

    public function requiresNetwork(): bool
    {
        return (bool) $this->require_network_for_checkin;
    }

    public function defaultStartShort(): string
    {
        return substr((string) $this->default_start_time, 0, 5);
    }

    public function defaultEndShort(): string
    {
        return substr((string) $this->default_end_time, 0, 5);
    }
}
