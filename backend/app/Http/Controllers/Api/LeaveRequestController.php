<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Services\AttendanceService;
use App\Support\LeaveTypeCatalog;
use App\Support\SqlOrder;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class LeaveRequestController extends Controller
{
    public function __construct(private readonly AttendanceService $attendance) {}

    /** Employee: my requests. Manager/Admin: the team's requests. */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = LeaveRequest::query()->with(['user:id,name,email,department,manager_id', 'decider:id,name']);

        // Asnje kerkese nuk kalon kufirin e biznesit.
        $query->where('business_id', $user->business_id);

        if ($user->isEmployee()) {
            $query->where('user_id', $user->id);
        } elseif ($user->isManager()) {
            $query->whereIn('user_id', $this->teamIds($user));
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        $requests = $query->orderByRaw(SqlOrder::byValues('status', ['pending', 'info_requested', 'approved', 'rejected']).' ASC')
            ->latest('id')
            ->limit(200)
            ->get();

        return response()->json([
            'requests' => $requests->map(fn (LeaveRequest $r) => $this->serialize($r))->all(),
        ]);
    }

    /**
     * Employee submits a leave request. Sick leave carries the medical
     * certificate (a photo straight from the phone camera is fine).
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'type' => ['required', Rule::in(LeaveRequest::TYPES)],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'description' => ['nullable', 'string', 'max:2000'],
            'certificate' => [
                'nullable',
                'file',
                'mimes:jpg,jpeg,png,webp,heic,pdf',
                'max:' . config('attendance.max_certificate_kb'),
            ],
        ]);

        $path = null;
        if ($request->hasFile('certificate')) {
            // storage/app/public/certificates -> served via `php artisan storage:link`
            $path = $request->file('certificate')->store('certificates', 'public');
        }

        $business = $user->business;

        $autoApprove = $data['type'] === 'sick_leave'
            && $path
            && ($business?->autoApprovesSick() ?? config('attendance.auto_approve_sick_with_certificate'));

        $leave = LeaveRequest::create([
            'business_id' => $user->business_id,
            'user_id' => $user->id,
            'type' => $data['type'],
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'description' => $data['description'] ?? null,
            'certificate_path' => $path,
            'status' => $autoApprove ? 'approved' : 'pending',
            'decided_at' => $autoApprove ? now() : null,
            'manager_note' => $autoApprove ? 'Aprovuar automatikisht: certifikata mjekësore e bashkëngjitur.' : null,
        ]);

        $this->attendance->notifySupervisors(
            $user,
            'leave_requested',
            $user->name . ' kërkoi ' . mb_strtolower(LeaveTypeCatalog::label($leave->type)),
            sprintf(
                '%s kërkoi %s nga %s deri më %s (%d ditë). Certifikata: %s.',
                $user->name,
                mb_strtolower(LeaveTypeCatalog::label($leave->type)),
                $leave->start_date->format('d.m.Y'),
                $leave->end_date->format('d.m.Y'),
                $leave->dayCount(),
                $path ? 'e ngarkuar' : 'e pangarkuar'
            ),
            'leave_requested:' . $leave->id,
            null,
            $leave
        );

        if ($autoApprove) {
            $this->applyToAttendance($leave);
        }

        return response()->json([
            'message' => $autoApprove
                ? 'Leja mjekësore u aprovua automatikisht (certifikata u bashkëngjit).'
                : 'Kërkesa u dërgua. Menaxheri juaj do ta shqyrtojë.',
            'request' => $this->serialize($leave->fresh(['user', 'decider'])),
        ], 201);
    }

    /** Manager/Admin: approve, reject, or ask for more information. */
    public function decide(Request $request, LeaveRequest $leaveRequest): JsonResponse
    {
        $user = $request->user();
        $this->authorizeSupervision($user, $leaveRequest->user_id);

        $data = $request->validate([
            'decision' => ['required', Rule::in(['approve', 'reject', 'request_info'])],
            'manager_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $status = match ($data['decision']) {
            'approve' => 'approved',
            'reject' => 'rejected',
            'request_info' => 'info_requested',
        };

        $leaveRequest->update([
            'status' => $status,
            'decided_by' => $user->id,
            'decided_at' => now(),
            'manager_note' => $data['manager_note'] ?? null,
        ]);

        $this->applyToAttendance($leaveRequest);

        $employee = $leaveRequest->user;
        $note = $data['manager_note'] ?? null;
        $title = match ($status) {
            'approved' => 'Kërkesa juaj për leje u aprovua',
            'rejected' => 'Kërkesa juaj për leje u refuzua',
            'info_requested' => 'Kërkohet më shumë informacion për lejen tuaj',
        };

        $this->attendance->createNotification(
            $employee->id,
            (int) $leaveRequest->business_id,
            $user->id,
            $status === 'info_requested' ? 'info_requested' : 'leave_decided',
            $title,
            sprintf(
                '%s (%s – %s): %s%s',
                LeaveTypeCatalog::label($leaveRequest->type),
                $leaveRequest->start_date->format('d.m.Y'),
                $leaveRequest->end_date->format('d.m.Y'),
                mb_strtolower(LeaveTypeCatalog::approvalLabel($status)),
                $note ? '. Shënim: ' . $note : '.'
            ),
            'leave_decided:' . $leaveRequest->id . ':' . $status . ':' . now()->timestamp,
            null,
            $leaveRequest
        );

        return response()->json([
            'message' => 'Kërkesa u shënua si "' . LeaveTypeCatalog::approvalLabel($status) . '".',
            'request' => $this->serialize($leaveRequest->fresh(['user', 'decider'])),
        ]);
    }

    public function destroy(Request $request, LeaveRequest $leaveRequest): JsonResponse
    {
        $user = $request->user();

        $ownRequest = $leaveRequest->user_id === $user->id;
        $adminOfSameBusiness = $user->isAdmin() && $leaveRequest->business_id === $user->business_id;

        if (! $ownRequest && ! $adminOfSameBusiness) {
            abort(403, 'Mund të tërhiqni vetëm kërkesat tuaja.');
        }

        if ($leaveRequest->certificate_path) {
            Storage::disk('public')->delete($leaveRequest->certificate_path);
        }

        $leaveRequest->delete();

        return response()->json(['message' => 'Kërkesa u tërhoq.']);
    }

    /**
     * Write the decision through to the attendance records it covers, so the
     * board and the monthly report agree with the approval.
     */
    private function applyToAttendance(LeaveRequest $leave): void
    {
        $cursor = $leave->start_date->copy();
        $end = $leave->end_date->copy();

        while ($cursor->lte($end)) {
            $this->attendance->syncRecord($leave->user, $cursor);
            $cursor->addDay();
        }
    }

    private function teamIds(User $manager): array
    {
        return User::where('business_id', $manager->business_id)
            ->where('manager_id', $manager->id)
            ->pluck('id')
            ->all();
    }

    private function authorizeSupervision(User $user, int $employeeId): void
    {
        $employee = User::find($employeeId);

        // Kufiri i pare dhe i palevizshem: i njejti biznes.
        if (! $employee || $employee->business_id !== $user->business_id) {
            abort(403, 'Ky punonjës nuk i përket biznesit tuaj.');
        }

        if ($user->isAdmin()) {
            return;
        }

        if ($user->isManager() && in_array($employeeId, $this->teamIds($user), true)) {
            return;
        }

        abort(403, 'Mund të vendosni vetëm për kërkesat e ekipit tuaj.');
    }

    private function serialize(LeaveRequest $r): array
    {
        return [
            'id' => $r->id,
            'user_id' => $r->user_id,
            'employee_name' => $r->user?->name,
            'department' => $r->user?->department,
            'type' => $r->type,
            'type_label' => LeaveTypeCatalog::label($r->type),
            'status_label' => LeaveTypeCatalog::approvalLabel($r->status),
            'start_date' => $r->start_date->format('Y-m-d'),
            'end_date' => $r->end_date->format('Y-m-d'),
            'days' => $r->dayCount(),
            'description' => $r->description,
            'has_certificate' => $r->has_certificate,
            'certificate_url' => $r->certificate_url,
            'status' => $r->status,
            'manager_note' => $r->manager_note,
            'decided_by' => $r->decider?->name,
            'decided_at' => $r->decided_at?->format('Y-m-d H:i'),
            'created_at' => $r->created_at?->format('Y-m-d H:i'),
        ];
    }
}
