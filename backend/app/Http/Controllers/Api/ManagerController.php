<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Services\AttendanceService;
use App\Support\ReasonCatalog;
use App\Support\StatusCatalog;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class ManagerController extends Controller
{
    public function __construct(private readonly AttendanceService $attendance) {}

    /**
     * The daily board. Opening it is what triggers the live evaluation:
     * absences are computed and "has not checked in" notifications are created
     * on the spot, so no cron job is needed.
     */
    public function dashboard(Request $request): JsonResponse
    {
        $request->validate(['date' => ['nullable', 'date']]);

        $manager = $request->user();
        $date = Carbon::parse($request->query('date', now()->toDateString()))->startOfDay();
        $now = $this->attendance->now();

        $employees = $this->visibleEmployees($manager);

        $this->attendance->evaluateTeam($employees, $date, $now);

        $rows = $employees
            ->map(fn (User $e) => $this->attendance->boardRow($e, $date, $now))
            ->sortBy([['status', 'asc'], ['name', 'asc']])
            ->values()
            ->all();

        $counts = collect($rows)->countBy('status');

        return response()->json([
            'date' => $date->format('Y-m-d'),
            'server_time' => $now->format('H:i'),
            'is_working_day' => $this->attendance->isWorkingDay($date, $manager->business),
            'rows' => $rows,
            'summary' => [
                'total' => count($rows),
                'present' => (int) $counts->get('present', 0),
                'late' => (int) $counts->get('late', 0),
                'absent' => (int) $counts->get('absent', 0),
                'on_leave' => (int) $counts->get('sick_leave', 0)
                    + (int) $counts->get('annual_leave', 0)
                    + (int) $counts->get('day_off', 0)
                    + (int) $counts->get('business_trip', 0),
                'awaiting' => (int) $counts->get('awaiting', 0),
                'unexcused' => collect($rows)->where('unexcused_absence', true)->count(),
                'pending_approvals' => LeaveRequest::whereIn('user_id', $employees->pluck('id'))
                    ->whereIn('status', ['pending', 'info_requested'])
                    ->count(),
            ],
        ]);
    }

    /**
     * Monthly report: one row per employee with late / sick / absent /
     * approved-leave totals.
     */
    public function monthlyReport(Request $request): JsonResponse
    {
        $request->validate(['month' => ['nullable', 'date_format:Y-m']]);

        $manager = $request->user();
        $month = Carbon::parse($request->query('month', now()->format('Y-m')) . '-01')->startOfMonth();
        $start = $month->format('Y-m-d');
        $end = $month->copy()->endOfMonth()->format('Y-m-d');

        $employees = $this->visibleEmployees($manager);

        // Make sure past working days in the month are materialised before counting.
        $this->backfill($employees, $month);

        $records = AttendanceRecord::query()
            ->whereIn('user_id', $employees->pluck('id'))
            ->whereBetween('work_date', [$start, $end])
            ->get()
            ->groupBy('user_id');

        $approvedLeave = LeaveRequest::query()
            ->whereIn('user_id', $employees->pluck('id'))
            ->where('status', 'approved')
            ->whereDate('start_date', '<=', $end)
            ->whereDate('end_date', '>=', $start)
            ->get()
            ->groupBy('user_id');

        $rows = $employees->map(function (User $employee) use ($records, $approvedLeave) {
            $mine = $records->get($employee->id, collect());
            $leaves = $approvedLeave->get($employee->id, collect());

            return [
                'user_id' => $employee->id,
                'name' => $employee->name,
                'department' => $employee->department,
                'expected_time' => $employee->expectedStartShort(),
                'late' => $mine->where('status', 'late')->count(),
                'late_minutes' => (int) $mine->sum('late_minutes'),
                'sick_days' => $mine->where('status', 'sick_leave')->count(),
                'absent' => $mine->where('status', 'absent')->count(),
                'unexcused_absent' => $mine->where('status', 'absent')->where('excused', false)->count(),
                'excused_absent' => $mine->where('status', 'absent')->where('excused', true)->count(),
                'approved_leave_days' => $leaves->whereIn('type', ['annual_leave', 'day_off', 'unpaid'])->sum(
                    fn (LeaveRequest $l) => $l->dayCount()
                ),
                'business_trip_days' => $mine->where('status', 'business_trip')->count(),
                'present_days' => $mine->whereIn('status', ['present', 'late'])->count(),
            ];
        })->values()->all();

        return response()->json([
            'month' => $month->format('Y-m'),
            'month_label' => $month->translatedFormat('F Y'),
            'rows' => $rows,
        ]);
    }

    /** Drill-down: one employee's month, day by day. */
    public function employeeDetail(Request $request, User $user): JsonResponse
    {
        $manager = $request->user();
        $this->assertVisible($manager, $user);

        $request->validate(['month' => ['nullable', 'date_format:Y-m']]);
        $month = Carbon::parse($request->query('month', now()->format('Y-m')) . '-01')->startOfMonth();

        $records = AttendanceRecord::query()
            ->where('user_id', $user->id)
            ->whereBetween('work_date', [
                $month->format('Y-m-d'),
                $month->copy()->endOfMonth()->format('Y-m-d'),
            ])
            ->orderBy('work_date')
            ->get();

        return response()->json([
            'employee' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'department' => $user->department,
                'phone' => $user->phone,
                'expected_time' => $user->expectedStartShort(),
            ],
            'month' => $month->format('Y-m'),
            'days' => $records->map(fn (AttendanceRecord $r) => [
                'work_date' => $r->work_date->format('Y-m-d'),
                'status' => $r->status,
                'status_label' => StatusCatalog::label($r->status),
                'status_color' => StatusCatalog::color($r->status),
                'status_emoji' => StatusCatalog::emoji($r->status),
                'checkin_time' => $r->checkin_time?->format('H:i'),
                'checkout_time' => $r->checkout_time?->format('H:i'),
                'late_minutes' => (int) $r->late_minutes,
                'reason_label' => ReasonCatalog::label($r->reason_category),
                'reason_note' => $r->reason_note,
                'excused' => (bool) $r->excused,
            ])->all(),
        ]);
    }

    /** Manager/Admin can also override a day (e.g. mark someone "left early"). */
    public function overrideDay(Request $request, User $user): JsonResponse
    {
        $manager = $request->user();
        $this->assertVisible($manager, $user);

        $data = $request->validate([
            'work_date' => ['required', 'date'],
            'status' => ['required', 'in:' . implode(',', AttendanceRecord::ALL_STATUSES)],
            'excused' => ['nullable', 'boolean'],
            'reason_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $date = Carbon::parse($data['work_date'])->startOfDay();

        $record = AttendanceRecord::firstOrNew([
            'user_id' => $user->id,
            'work_date' => $date->format('Y-m-d'),
        ]);

        $record->expected_start_time = substr((string) $user->expected_start_time, 0, 8);
        $record->status = $data['status'];
        $record->excused = $data['excused'] ?? in_array($data['status'], AttendanceRecord::LEAVE_STATUSES, true);
        $record->reason_note = $data['reason_note'] ?? $record->reason_note;
        $record->reported_at = $record->reported_at ?? now();
        $record->save();

        $this->attendance->createNotification(
            $user->id,
            (int) $user->business_id,
            $manager->id,
            'day_overridden',
            'Prezenca juaj për ' . $date->format('d.m.Y') . ' u përditësua',
            $manager->name . ' e vendosi statusin tuaj si "' . StatusCatalog::label($data['status']) . '".',
            'override:' . $user->id . ':' . $date->format('Y-m-d') . ':' . now()->timestamp,
            $record
        );

        return response()->json(['message' => 'Prezenca u përditësua.']);
    }

    // ------------------------------------------------------------------ scope

    /**
     * Admini i biznesit sheh te gjithe punonjesit e biznesit te vet; menaxheri
     * sheh vetem ata qe raportojne tek ai. Asnjeri nuk sheh jashte biznesit.
     */
    private function visibleEmployees(User $manager): Collection
    {
        $query = User::query()
            ->with('business')
            ->where('is_active', true)
            ->where('business_id', $manager->business_id);

        if ($manager->isAdmin()) {
            $query->whereIn('role', ['manager', 'employee']);
        } else {
            $query->where('manager_id', $manager->id);
        }

        return $query->orderBy('name')->get();
    }

    private function assertVisible(User $manager, User $employee): void
    {
        if ($employee->business_id !== $manager->business_id) {
            abort(403, 'Ky punonjës nuk i përket biznesit tuaj.');
        }
        if ($manager->isAdmin()) {
            return;
        }
        if ($employee->manager_id === $manager->id) {
            return;
        }

        abort(403, 'Ky punonjës nuk është pjesë e ekipit tuaj.');
    }

    /** Create the missing absent rows for past working days in the month. */
    private function backfill(Collection $employees, Carbon $month): void
    {
        $now = $this->attendance->now();
        $cursor = $month->copy();
        $end = $month->copy()->endOfMonth();

        if ($end->gt($now)) {
            $end = $now->copy();
        }

        while ($cursor->lte($end)) {
            if ($this->attendance->isWorkingDay($cursor, $employees->first()?->business)) {
                foreach ($employees as $employee) {
                    $this->attendance->syncRecord($employee, $cursor, $now);
                }
            }
            $cursor->addDay();
        }
    }
}
