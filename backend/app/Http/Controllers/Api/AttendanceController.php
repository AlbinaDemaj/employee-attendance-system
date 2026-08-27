<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Services\AttendanceService;
use App\Services\NetworkGuard;
use App\Support\ReasonCatalog;
use App\Support\StatusCatalog;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AttendanceController extends Controller
{
    public function __construct(
        private readonly AttendanceService $attendance,
        private readonly NetworkGuard $network,
    ) {}

    /** The employee's own view of today. */
    public function today(Request $request): JsonResponse
    {
        $user = $request->user();
        $business = $this->attendance->businessOf($user);
        $now = $this->attendance->now();
        $date = $now->copy()->startOfDay();

        // Keep the stored record honest before reading it.
        $this->attendance->syncRecord($user, $date, $now);
        $record = $this->attendance->recordFor($user, $date);

        $status = $this->attendance->liveStatus($user, $date, $record, $now);
        $expected = $this->attendance->expectedStartFor($user, $date);
        $overdue = $this->attendance->minutesOverdue($user, $date, $now);

        $checkedIn = (bool) $record?->checkin_time;
        $onLeave = $record && $record->isOnLeave();
        $hasReason = (bool) $record?->reason_category;

        // Prompt shown when the shift started and nothing has been reported.
        $needsReason = ! $checkedIn
            && ! $onLeave
            && ! $hasReason
            && $this->attendance->isWorkingDay($date, $business)
            && $now->gte($expected);

        // A late check-in without an explanation still owes a reason.
        $needsLateReason = $checkedIn && $record->status === 'late' && ! $hasReason;

        return response()->json([
            'date' => $date->format('Y-m-d'),
            'server_time' => $now->format('H:i'),
            'is_working_day' => $this->attendance->isWorkingDay($date, $business),
            'network' => $this->networkState($request),
            'expected_time' => $expected->format('H:i'),
            'status' => $status,
            'status_label' => StatusCatalog::label($status),
            'status_color' => StatusCatalog::color($status),
            'status_emoji' => StatusCatalog::emoji($status),
            'checked_in' => $checkedIn,
            'checkin_time' => $record?->checkin_time?->format('H:i'),
            'checkout_time' => $record?->checkout_time?->format('H:i'),
            'late_minutes' => (int) ($record?->late_minutes ?? 0),
            'minutes_overdue' => $overdue,
            'reason_category' => $record?->reason_category,
            'reason_label' => ReasonCatalog::label($record?->reason_category),
            'reason_note' => $record?->reason_note,
            'reported_at' => $record?->reported_at?->format('H:i'),
            'excused' => (bool) $record?->excused,
            'needs_reason' => $needsReason,
            'needs_late_reason' => $needsLateReason,
            'prompt' => ($needsReason || $needsLateReason)
                ? sprintf(
                    'Ishit pritur në orën %s. %s',
                    $expected->format('H:i'),
                    $needsLateReason
                        ? sprintf('U paraqitët me %d minuta vonesë. Cila ishte arsyeja?', (int) $record->late_minutes)
                        : 'Cila është arsyeja pse nuk keni bërë ende check-in?'
                )
                : null,
        ]);
    }

    public function checkIn(Request $request): JsonResponse
    {
        $data = $request->validate([
            'reason_category' => ['nullable', Rule::in(ReasonCatalog::keys())],
            'reason_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $user = $request->user();
        $business = $this->attendance->businessOf($user);

        // Check-in vetëm nga rrjeti i punës. Arsyet dhe lejet nuk e kanë këtë
        // kufizim - ato raportohen nga kudo.
        if ($business && ! $this->network->allows($business, $request->ip())) {
            return response()->json([
                'message' => 'Check-in-i lejohet vetëm kur jeni të lidhur me rrjetin e punës. '
                    . 'Adresa juaj aktuale (' . $request->ip() . ') nuk njihet si rrjet i biznesit. '
                    . 'Nëse jeni jashtë, përdorni "Raporto arsyen" ose kërkoni leje.',
                'network' => $this->networkState($request),
            ], 422);
        }

        $record = $this->attendance->checkIn(
            $user,
            $data['reason_category'] ?? null,
            $data['reason_note'] ?? null,
            null,
            $request->ip()
        );

        return response()->json([
            'message' => $record->status === 'late'
                ? sprintf('Check-in në orën %s — %d minuta vonesë.', $record->checkin_time->format('H:i'), $record->late_minutes)
                : sprintf('Check-in në orën %s. Ditë të mbarë!', $record->checkin_time->format('H:i')),
            'record' => $this->serialize($record),
        ]);
    }

    public function checkOut(Request $request): JsonResponse
    {
        $user = $request->user();
        $business = $this->attendance->businessOf($user);

        if ($business && ! $this->network->allows($business, $request->ip())) {
            return response()->json([
                'message' => 'Check-out-i lejohet vetëm nga rrjeti i punës.',
                'network' => $this->networkState($request),
            ], 422);
        }

        $record = $this->attendance->checkOut($user, null, $request->ip());

        return response()->json([
            'message' => 'Dolët nga puna në orën ' . $record->checkout_time->format('H:i') . '.',
            'record' => $this->serialize($record),
        ]);
    }

    /** Answer the "why aren't you here / why were you late" prompt. */
    public function reportReason(Request $request): JsonResponse
    {
        $data = $request->validate([
            'reason_category' => ['required', Rule::in(ReasonCatalog::keys())],
            'reason_note' => ['nullable', 'string', 'max:2000'],
            'date' => ['nullable', 'date'],
        ]);

        $date = isset($data['date'])
            ? Carbon::parse($data['date'])->startOfDay()
            : null;

        $record = $this->attendance->reportReason(
            $request->user(),
            $data['reason_category'],
            $data['reason_note'] ?? null,
            $date
        );

        return response()->json([
            'message' => 'Faleminderit — menaxheri juaj u njoftua.',
            'record' => $this->serialize($record),
        ]);
    }

    /** The employee's own month, plus a small summary. */
    public function history(Request $request): JsonResponse
    {
        $data = $request->validate([
            'month' => ['nullable', 'date_format:Y-m'],
        ]);

        $month = Carbon::parse(($data['month'] ?? now()->format('Y-m')) . '-01')->startOfMonth();

        $records = AttendanceRecord::query()
            ->where('user_id', $request->user()->id)
            ->whereBetween('work_date', [$month->format('Y-m-d'), $month->copy()->endOfMonth()->format('Y-m-d')])
            ->orderBy('work_date')
            ->get();

        return response()->json([
            'month' => $month->format('Y-m'),
            'records' => $records->map(fn (AttendanceRecord $r) => $this->serialize($r))->all(),
            'summary' => [
                'present' => $records->whereIn('status', ['present', 'late'])->count(),
                'late' => $records->where('status', 'late')->count(),
                'late_minutes_total' => (int) $records->sum('late_minutes'),
                'absent' => $records->where('status', 'absent')->count(),
                'unexcused_absent' => $records->where('status', 'absent')->where('excused', false)->count(),
                'sick_days' => $records->where('status', 'sick_leave')->count(),
                'annual_leave_days' => $records->where('status', 'annual_leave')->count(),
            ],
        ]);
    }

    /**
     * A po vjen kërkesa nga rrjeti i punës? Shfletuesi nuk e lexon dot emrin e
     * WiFi-t, prandaj identifikimi bëhet me IP-në publike të biznesit.
     */
    private function networkState(Request $request): array
    {
        $user = $request->user();
        $business = $this->attendance->businessOf($user);
        $ip = $request->ip();

        if (! $business) {
            return ['required' => false, 'allowed' => true, 'ip' => $ip, 'network_label' => null];
        }

        $match = $this->network->matchingNetwork($business, $ip);

        return [
            'required' => $business->requiresNetwork(),
            'allowed' => $this->network->allows($business, $ip),
            'ip' => $ip,
            'network_label' => $match?->label,
        ];
    }

    private function serialize(AttendanceRecord $record): array
    {
        return [
            'id' => $record->id,
            'work_date' => $record->work_date->format('Y-m-d'),
            'status' => $record->status,
            'status_label' => StatusCatalog::label($record->status),
            'status_color' => StatusCatalog::color($record->status),
            'status_emoji' => StatusCatalog::emoji($record->status),
            'expected_time' => substr((string) $record->expected_start_time, 0, 5),
            'checkin_time' => $record->checkin_time?->format('H:i'),
            'checkout_time' => $record->checkout_time?->format('H:i'),
            'checkin_ip' => $record->checkin_ip,
            'late_minutes' => (int) $record->late_minutes,
            'reason_category' => $record->reason_category,
            'reason_label' => ReasonCatalog::label($record->reason_category),
            'reason_note' => $record->reason_note,
            'excused' => (bool) $record->excused,
            'reported_at' => $record->reported_at?->format('H:i'),
            'leave_request_id' => $record->leave_request_id,
        ];
    }
}
