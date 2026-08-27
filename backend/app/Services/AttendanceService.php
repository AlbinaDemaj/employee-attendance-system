<?php

namespace App\Services;

use App\Models\AttendanceRecord;
use App\Models\Business;
use App\Models\LeaveRequest;
use App\Models\Notification;
use App\Models\User;
use App\Support\LeaveTypeCatalog;
use App\Support\ReasonCatalog;
use App\Support\StatusCatalog;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Gjithçka rreth "kush është në punë, kush jo, dhe pse".
 *
 * Vonesa dhe mungesa llogariten drejtpërdrejt sa herë hapet një dashboard -
 * backend-i krahason orën aktuale me orën e pritur të secilit punonjës. Nuk ka
 * scheduler në sfond.
 *
 * Çdo rregull (ditët e punës, pragjet, aprovimi automatik) vjen nga biznesi të
 * cilit i përket punonjësi, jo nga një konfigurim global.
 */
class AttendanceService
{
    /** Lloji i lejes -> statusi që shfaqet në tabelë. */
    public const LEAVE_TYPE_STATUS = [
        'sick_leave' => 'sick_leave',
        'annual_leave' => 'annual_leave',
        'day_off' => 'day_off',
        'business_trip' => 'business_trip',
        'unpaid' => 'absent',
    ];

    public function now(): Carbon
    {
        return Carbon::now();
    }

    public function businessOf(User $user): ?Business
    {
        return $user->relationLoaded('business') ? $user->business : $user->business()->first();
    }

    public function isWorkingDay(CarbonInterface $date, ?Business $business = null): bool
    {
        $days = $business?->workingDays() ?? config('attendance.working_days');

        return in_array($date->dayOfWeekIso, $days, true);
    }

    /** Momenti kur punonjësi pritej në punë atë ditë. */
    public function expectedStartFor(User $user, CarbonInterface $date): Carbon
    {
        $time = $user->expected_start_time
            ?: $this->businessOf($user)?->default_start_time
            ?: config('attendance.default_expected_start_time') . ':00';

        return Carbon::parse($date->format('Y-m-d') . ' ' . substr((string) $time, 0, 8));
    }

    public function approvedLeaveFor(User $user, CarbonInterface $date): ?LeaveRequest
    {
        return LeaveRequest::query()
            ->where('user_id', $user->id)
            ->where('status', 'approved')
            ->whereDate('start_date', '<=', $date->format('Y-m-d'))
            ->whereDate('end_date', '>=', $date->format('Y-m-d'))
            ->latest('id')
            ->first();
    }

    /** Çdo kërkesë leje aktive (edhe në pritje) që mbulon këtë datë. */
    public function anyLeaveFor(User $user, CarbonInterface $date): ?LeaveRequest
    {
        return LeaveRequest::query()
            ->where('user_id', $user->id)
            ->whereIn('status', ['pending', 'approved', 'info_requested'])
            ->whereDate('start_date', '<=', $date->format('Y-m-d'))
            ->whereDate('end_date', '>=', $date->format('Y-m-d'))
            ->orderByRaw("FIELD(status, 'approved', 'info_requested', 'pending') ASC")
            ->latest('id')
            ->first();
    }

    public function recordFor(User $user, CarbonInterface $date): ?AttendanceRecord
    {
        return AttendanceRecord::query()
            ->where('user_id', $user->id)
            ->whereDate('work_date', $date->format('Y-m-d'))
            ->first();
    }

    /**
     * Sjell rreshtin e ruajtur në përputhje me realitetin: leja e aprovuar ka
     * përparësi, dhe një check-in që mungon pas pragut ruhet si mungesë e
     * pajustifikuar, që të dalë në raportin mujor.
     */
    public function syncRecord(User $user, CarbonInterface $date, ?Carbon $now = null): ?AttendanceRecord
    {
        $now ??= $this->now();
        $business = $this->businessOf($user);
        $record = $this->recordFor($user, $date);
        $leave = $this->approvedLeaveFor($user, $date);

        // 1. Leja e aprovuar dikton statusin.
        if ($leave) {
            $status = self::LEAVE_TYPE_STATUS[$leave->type] ?? 'absent';

            return $this->persist($user, $date, [
                'status' => $status,
                'excused' => true,
                'leave_request_id' => $leave->id,
                'reason_category' => $record?->reason_category ?? ($leave->type === 'sick_leave' ? 'sick' : 'approved_leave'),
                'reason_note' => $record?->reason_note ?? $leave->description,
                'reported_at' => $record?->reported_at ?? $leave->created_at,
            ], $record);
        }

        // 1b. Një leje e aprovuar më parë e rrëzuar nuk duhet ta ngjyrosë ditën.
        if ($record && $record->leave_request_id) {
            $record->leave_request_id = null;
            $record->excused = false;
            if ($record->isOnLeave()) {
                $record->status = $record->checkin_time
                    ? ($record->late_minutes > 0 ? 'late' : 'present')
                    : 'absent';
            }
            $record->save();
        }

        // 2. Ka bërë check-in ose ka dhënë arsye - mos e prek.
        if ($record && ($record->checkin_time || $record->reported_at)) {
            return $record;
        }

        // 3. Asgjë e raportuar: shënoje mungesë vetëm pasi kalon pragu.
        if (! $this->isWorkingDay($date, $business)) {
            return $record;
        }

        $threshold = $this->expectedStartFor($user, $date)
            ->addMinutes($business?->alertAfterMinutes() ?? (int) config('attendance.manager_alert_after_minutes'));

        if ($now->lt($threshold)) {
            return $record;
        }

        return $this->persist($user, $date, [
            'status' => 'absent',
            'excused' => false,
        ], $record);
    }

    private function persist(User $user, CarbonInterface $date, array $attributes, ?AttendanceRecord $record): AttendanceRecord
    {
        $record ??= new AttendanceRecord();

        $record->business_id = $user->business_id;
        $record->user_id = $user->id;
        $record->work_date = $date->format('Y-m-d');
        $record->expected_start_time = $this->expectedStartFor($user, $date)->format('H:i:s');
        $record->fill($attributes);
        $record->save();

        return $record;
    }

    /**
     * Statusi që shfaqet tani, i cili para fillimit të turnit ndryshon nga ai i
     * ruajtur ("në pritje" në vend të "mungesë").
     */
    public function liveStatus(User $user, CarbonInterface $date, ?AttendanceRecord $record, ?Carbon $now = null): string
    {
        $now ??= $this->now();

        if ($record && ($record->isOnLeave() || $record->checkin_time || $record->reported_at)) {
            return $record->status;
        }

        if (! $this->isWorkingDay($date, $this->businessOf($user))) {
            return 'day_off';
        }

        $expected = $this->expectedStartFor($user, $date);

        if ($now->lt($expected)) {
            return 'awaiting';
        }

        return $record?->status ?? 'absent';
    }

    public function minutesOverdue(User $user, CarbonInterface $date, ?Carbon $now = null): int
    {
        $now ??= $this->now();
        $expected = $this->expectedStartFor($user, $date);

        if ($now->lte($expected)) {
            return 0;
        }

        return (int) floor($expected->diffInMinutes($now, true));
    }

    // -------------------------------------------------------------- veprimet

    public function checkIn(
        User $user,
        ?string $reasonCategory = null,
        ?string $reasonNote = null,
        ?Carbon $now = null,
        ?string $ip = null
    ): AttendanceRecord {
        $now ??= $this->now();
        $business = $this->businessOf($user);
        $date = $now->copy()->startOfDay();
        $record = $this->recordFor($user, $date);

        if ($record && $record->checkin_time) {
            return $record; // idempotent
        }

        $expected = $this->expectedStartFor($user, $date);
        $grace = $business?->lateGraceMinutes() ?? (int) config('attendance.late_grace_minutes');
        $lateMinutes = 0;
        $status = 'present';

        if ($now->gt($expected->copy()->addMinutes($grace))) {
            $lateMinutes = (int) floor($expected->diffInMinutes($now, true));
            $status = 'late';
        }

        if ($leave = $this->approvedLeaveFor($user, $date)) {
            $status = self::LEAVE_TYPE_STATUS[$leave->type] ?? $status;
        }

        $category = $reasonCategory ?: ($record ? $record->reason_category : null);

        $record = $this->persist($user, $date, [
            'status' => $status,
            'checkin_time' => $now,
            'checkin_ip' => $ip,
            'late_minutes' => $lateMinutes,
            'reason_category' => $category,
            'reason_note' => $reasonNote ?: ($record ? $record->reason_note : null),
            'excused' => $status === 'late' ? ReasonCatalog::isAutoExcused($category) : true,
            'reported_at' => $reasonCategory ? $now : ($record ? $record->reported_at : null),
        ], $record);

        if ($status === 'late') {
            $this->notifySupervisors(
                $user,
                'late_checkin',
                $user->name . ' u paraqit me ' . $lateMinutes . ' minuta vonesë',
                sprintf(
                    '%s pritej në orën %s dhe bëri check-in në %s (%d minuta vonesë).%s',
                    $user->name,
                    $expected->format('H:i'),
                    $now->format('H:i'),
                    $lateMinutes,
                    $category ? ' Arsyeja: ' . ReasonCatalog::label($category) . '.' : ' Ende pa arsye të dhënë.'
                ),
                'late_checkin:' . $user->id . ':' . $date->format('Y-m-d'),
                $record
            );
        }

        return $record;
    }

    public function checkOut(User $user, ?Carbon $now = null, ?string $ip = null): AttendanceRecord
    {
        $now ??= $this->now();
        $date = $now->copy()->startOfDay();
        $record = $this->recordFor($user, $date);

        if (! $record || ! $record->checkin_time) {
            abort(422, 'Nuk keni bërë check-in sot.');
        }

        $record->checkout_time = $now;
        $record->checkout_ip = $ip;
        $record->save();

        return $record;
    }

    /** Punonjësi shpjegon pse nuk është në punë (ose pse u vonua). */
    public function reportReason(
        User $user,
        string $category,
        ?string $note = null,
        ?CarbonInterface $date = null,
        ?Carbon $now = null
    ): AttendanceRecord {
        $now ??= $this->now();
        $date ??= $now->copy()->startOfDay();
        $record = $this->recordFor($user, $date);
        $checkedIn = $record && $record->checkin_time;

        $status = $checkedIn ? $record->status : 'absent';
        if (! $checkedIn && $category === 'working_remotely') {
            $status = 'present';
        }
        if (! $checkedIn && $category === 'business_assignment') {
            $status = 'business_trip';
        }

        $record = $this->persist($user, $date, [
            'status' => $status,
            'reason_category' => $category,
            'reason_note' => $note,
            'reported_at' => $now,
            'excused' => ReasonCatalog::isAutoExcused($category),
        ], $record);

        $this->notifySupervisors(
            $user,
            'reason_reported',
            $user->name . ': ' . ReasonCatalog::label($category),
            sprintf(
                '%s raportoi "%s" për datën %s.%s',
                $user->name,
                ReasonCatalog::label($category),
                $date->format('d.m.Y'),
                $note ? ' Shënim: ' . $note : ''
            ),
            'reason_reported:' . $user->id . ':' . $date->format('Y-m-d') . ':' . $category,
            $record
        );

        return $record;
    }

    // ------------------------------------------------------------- njoftimet

    /**
     * Thirret kur një menaxher/admin hap tabelën: vlerëson çdo punonjës për atë
     * ditë dhe krijon njoftimet "nuk ka bërë check-in" që ende s'ekzistojnë.
     */
    public function evaluateTeam(Collection $employees, CarbonInterface $date, ?Carbon $now = null): void
    {
        $now ??= $this->now();

        foreach ($employees as $employee) {
            $business = $this->businessOf($employee);
            $record = $this->syncRecord($employee, $date, $now);

            if (! $this->isWorkingDay($date, $business)) {
                continue;
            }
            if ($record && ($record->checkin_time || $record->isOnLeave() || $record->reported_at)) {
                continue;
            }

            $alertAfter = $business?->alertAfterMinutes() ?? (int) config('attendance.manager_alert_after_minutes');
            $expected = $this->expectedStartFor($employee, $date);

            if ($now->lt($expected->copy()->addMinutes($alertAfter))) {
                continue;
            }

            $overdue = (int) floor($expected->diffInMinutes($now, true));

            $this->notifySupervisors(
                $employee,
                'missing_checkin',
                $employee->name . ' nuk ka bërë check-in',
                sprintf(
                    '%s nuk ka bërë check-in %d minuta pas fillimit të turnit (pritej në orën %s).',
                    $employee->name,
                    $overdue,
                    $expected->format('H:i')
                ),
                'missing_checkin:' . $employee->id . ':' . $date->format('Y-m-d'),
                $record
            );
        }
    }

    /**
     * Njofto menaxherin e punonjësit dhe adminët e TË NJËJTIT biznes.
     * Asnjë njoftim nuk kalon kurrë kufirin e biznesit.
     */
    public function notifySupervisors(
        User $employee,
        string $type,
        string $title,
        string $body,
        string $dedupeKey,
        ?AttendanceRecord $record = null,
        ?LeaveRequest $leave = null
    ): void {
        if (! $employee->business_id) {
            return;
        }

        $recipients = User::query()
            ->where('business_id', $employee->business_id)
            ->where(function ($q) use ($employee) {
                $q->where('id', $employee->manager_id)
                    ->orWhere('role', 'admin');
            })
            ->where('id', '!=', $employee->id)
            ->pluck('id');

        foreach ($recipients as $recipientId) {
            $this->createNotification(
                (int) $recipientId,
                (int) $employee->business_id,
                $employee->id,
                $type,
                $title,
                $body,
                $dedupeKey . ':to' . $recipientId,
                $record,
                $leave
            );
        }
    }

    public function createNotification(
        int $recipientId,
        int $businessId,
        ?int $subjectUserId,
        string $type,
        string $title,
        string $body,
        string $dedupeKey,
        ?AttendanceRecord $record = null,
        ?LeaveRequest $leave = null
    ): void {
        // dedupe_key është unik, pra ky është një insert "vetëm një herë".
        if (Notification::where('dedupe_key', $dedupeKey)->exists()) {
            return;
        }

        try {
            Notification::create([
                'business_id' => $businessId,
                'user_id' => $recipientId,
                'subject_user_id' => $subjectUserId,
                'type' => $type,
                'title' => $title,
                'body' => $body,
                'attendance_record_id' => $record?->id,
                'leave_request_id' => $leave?->id,
                'dedupe_key' => $dedupeKey,
            ]);
        } catch (\Illuminate\Database\QueryException $e) {
            // Përplasje me një kërkesë paralele që futi të njëjtin dedupe_key.
        }
    }

    // ----------------------------------------------------------- serializimi

    /** Një rresht i tabelës së menaxherit. */
    public function boardRow(User $employee, CarbonInterface $date, ?Carbon $now = null): array
    {
        $now ??= $this->now();
        $record = $this->recordFor($employee, $date);
        $leave = $this->anyLeaveFor($employee, $date);
        $status = $this->liveStatus($employee, $date, $record, $now);
        $expected = $this->expectedStartFor($employee, $date);

        $lateMinutes = $record ? (int) $record->late_minutes : 0;
        if ((! $record || ! $record->checkin_time) && $status === 'absent') {
            $lateMinutes = $this->minutesOverdue($employee, $date, $now);
        }

        $approvalStatus = $leave?->status;
        if (! $approvalStatus && $record && $record->reported_at) {
            $approvalStatus = $record->excused ? 'approved' : 'pending';
        }

        return [
            'user_id' => $employee->id,
            'name' => $employee->name,
            'email' => $employee->email,
            'department' => $employee->department,
            'expected_time' => $expected->format('H:i'),
            'status' => $status,
            'status_label' => StatusCatalog::label($status),
            'status_color' => StatusCatalog::color($status),
            'status_emoji' => StatusCatalog::emoji($status),
            'checkin_time' => $record?->checkin_time?->format('H:i'),
            'checkout_time' => $record?->checkout_time?->format('H:i'),
            'checkin_ip' => $record?->checkin_ip,
            'late_minutes' => $lateMinutes,
            'reported_at' => $record?->reported_at?->format('H:i'),
            'reason_category' => $record?->reason_category,
            'reason_label' => ReasonCatalog::label($record?->reason_category),
            'reason_note' => $record?->reason_note,
            'excused' => (bool) ($record?->excused),
            'until' => $leave?->end_date?->format('Y-m-d'),
            'leave_request_id' => $leave?->id,
            'leave_type' => $leave?->type,
            'leave_type_label' => LeaveTypeCatalog::label($leave?->type),
            'has_certificate' => (bool) $leave?->certificate_path,
            'certificate_url' => $leave?->certificate_url,
            'approval_status' => $approvalStatus,
            'approval_label' => LeaveTypeCatalog::approvalLabel($approvalStatus),
            'manager_note' => $leave?->manager_note,
            'unexcused_absence' => $status === 'absent'
                && ! ($record && $record->excused)
                && ! ($record && $record->reported_at),
        ];
    }
}
