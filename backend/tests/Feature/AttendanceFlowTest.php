<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\Business;
use App\Models\LeaveRequest;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AttendanceFlowTest extends TestCase
{
    use RefreshDatabase;

    private Business $business;
    private User $manager;
    private User $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->business = Business::create([
            'name' => 'Biznes Testues',
            'working_days' => [1, 2, 3, 4, 5],
            'late_grace_minutes' => 0,
            'manager_alert_after_minutes' => 15,
            'require_network_for_checkin' => false, // rrjeti testohet veçmas
        ]);

        $this->manager = User::create([
            'business_id' => $this->business->id,
            'name' => 'Manager', 'email' => 'm@test.com', 'password' => 'password',
            'role' => 'manager', 'expected_start_time' => '08:00:00',
        ]);

        $this->employee = User::create([
            'business_id' => $this->business->id,
            'name' => 'Ardit', 'email' => 'e@test.com', 'password' => 'password',
            'role' => 'employee', 'expected_start_time' => '08:00:00',
            'manager_id' => $this->manager->id,
        ]);
    }

    /** A Monday, so it is always a working day regardless of when tests run. */
    private function freezeAt(string $time): Carbon
    {
        $moment = Carbon::parse('2026-08-17 ' . $time); // Monday
        Carbon::setTestNow($moment);

        return $moment;
    }

    public function test_login_returns_a_token_and_the_role(): void
    {
        $response = $this->postJson('/api/login', [
            'email' => 'e@test.com',
            'password' => 'password',
        ]);

        $response->assertOk()
            ->assertJsonPath('user.role', 'employee')
            ->assertJsonStructure(['token', 'user' => ['id', 'name', 'role', 'expected_start_time']]);
    }

    public function test_wrong_password_is_rejected(): void
    {
        $this->postJson('/api/login', ['email' => 'e@test.com', 'password' => 'nope'])
            ->assertStatus(422);
    }

    public function test_an_employee_cannot_reach_manager_or_admin_routes(): void
    {
        $this->actingAs($this->employee, 'sanctum')
            ->getJson('/api/manager/dashboard')
            ->assertForbidden();

        $this->actingAs($this->employee, 'sanctum')
            ->getJson('/api/admin/users')
            ->assertForbidden();
    }

    public function test_checking_in_on_time_is_present(): void
    {
        $this->freezeAt('07:55');

        $this->actingAs($this->employee, 'sanctum')
            ->postJson('/api/attendance/check-in')
            ->assertOk()
            ->assertJsonPath('record.status', 'present')
            ->assertJsonPath('record.late_minutes', 0);
    }

    public function test_checking_in_late_computes_the_minutes_and_asks_for_a_reason(): void
    {
        $this->freezeAt('08:27');

        $this->actingAs($this->employee, 'sanctum')
            ->postJson('/api/attendance/check-in')
            ->assertOk()
            ->assertJsonPath('record.status', 'late')
            ->assertJsonPath('record.late_minutes', 27);

        $this->actingAs($this->employee, 'sanctum')
            ->getJson('/api/attendance/today')
            ->assertOk()
            ->assertJsonPath('needs_late_reason', true)
            ->assertJsonPath('prompt', 'Ishit pritur në orën 08:00. U paraqitët me 27 minuta vonesë. Cila ishte arsyeja?');
    }

    public function test_a_late_checkin_notifies_the_manager(): void
    {
        $this->freezeAt('08:27');

        $this->actingAs($this->employee, 'sanctum')->postJson('/api/attendance/check-in');

        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->manager->id,
            'subject_user_id' => $this->employee->id,
            'type' => 'late_checkin',
        ]);
    }

    public function test_before_the_shift_starts_the_employee_is_not_marked_absent(): void
    {
        $this->freezeAt('07:30');

        $this->actingAs($this->employee, 'sanctum')
            ->getJson('/api/attendance/today')
            ->assertOk()
            ->assertJsonPath('status', 'awaiting')
            ->assertJsonPath('needs_reason', false);

        $this->assertDatabaseCount('attendance_records', 0);
    }

    public function test_the_prompt_appears_once_the_expected_time_has_passed(): void
    {
        $this->freezeAt('08:05');

        $this->actingAs($this->employee, 'sanctum')
            ->getJson('/api/attendance/today')
            ->assertOk()
            ->assertJsonPath('needs_reason', true)
            ->assertJsonPath('prompt', 'Ishit pritur në orën 08:00. Cila është arsyeja pse nuk keni bërë ende check-in?');
    }

    public function test_opening_the_manager_dashboard_creates_the_missing_checkin_alert(): void
    {
        $this->freezeAt('08:20'); // past the 15 minute threshold

        $this->actingAs($this->manager, 'sanctum')
            ->getJson('/api/manager/dashboard')
            ->assertOk()
            ->assertJsonPath('rows.0.status', 'absent')
            ->assertJsonPath('rows.0.unexcused_absence', true);

        $notification = Notification::where('type', 'missing_checkin')->first();
        $this->assertNotNull($notification);
        $this->assertSame($this->manager->id, $notification->user_id);
        $this->assertStringContainsString('nuk ka bërë check-in 20 minuta', $notification->body);
    }

    public function test_the_missing_checkin_alert_is_only_created_once(): void
    {
        $this->freezeAt('08:20');

        $this->actingAs($this->manager, 'sanctum')->getJson('/api/manager/dashboard');
        $this->actingAs($this->manager, 'sanctum')->getJson('/api/manager/dashboard');
        $this->actingAs($this->manager, 'sanctum')->getJson('/api/manager/dashboard');

        $this->assertSame(1, Notification::where('type', 'missing_checkin')->count());
    }

    public function test_no_alert_before_the_threshold(): void
    {
        $this->freezeAt('08:10'); // only 10 minutes late, threshold is 15

        $this->actingAs($this->manager, 'sanctum')->getJson('/api/manager/dashboard')->assertOk();

        $this->assertSame(0, Notification::where('type', 'missing_checkin')->count());
    }

    public function test_reporting_a_reason_clears_the_unexcused_flag_and_notifies_the_manager(): void
    {
        $this->freezeAt('08:20');

        $this->actingAs($this->employee, 'sanctum')
            ->postJson('/api/attendance/reason', [
                'reason_category' => 'transport_problem',
                'reason_note' => 'Bllokim trafiku.',
            ])
            ->assertOk();

        $this->assertDatabaseHas('attendance_records', [
            'user_id' => $this->employee->id,
            'reason_category' => 'transport_problem',
            'reason_note' => 'Bllokim trafiku.',
        ]);

        $this->actingAs($this->manager, 'sanctum')
            ->getJson('/api/manager/dashboard')
            ->assertJsonPath('rows.0.unexcused_absence', false)
            ->assertJsonPath('rows.0.approval_status', 'pending');

        $this->assertDatabaseHas('notifications', ['type' => 'reason_reported']);
    }

    public function test_an_auto_excused_reason_needs_no_manager_decision(): void
    {
        $this->freezeAt('08:20');

        $this->actingAs($this->employee, 'sanctum')
            ->postJson('/api/attendance/reason', ['reason_category' => 'working_remotely'])
            ->assertOk()
            ->assertJsonPath('record.excused', true)
            ->assertJsonPath('record.status', 'present');
    }

    public function test_sick_leave_with_a_certificate_can_be_approved_and_colours_the_day(): void
    {
        $this->freezeAt('08:05');
        Storage::fake('public'); // keep test uploads out of the real storage dir

        $file = UploadedFile::fake()->image('certificate.jpg');

        $created = $this->actingAs($this->employee, 'sanctum')
            ->postJson('/api/leave-requests', [
                'type' => 'sick_leave',
                'start_date' => '2026-08-17',
                'end_date' => '2026-08-18',
                'description' => 'Kam temperaturë dhe nuk mund të vij sot.',
                'certificate' => $file,
            ])
            ->assertCreated()
            ->assertJsonPath('request.status', 'pending')
            ->assertJsonPath('request.has_certificate', true)
            ->json('request.id');

        // The manager sees it as pending with the certificate attached.
        $this->actingAs($this->manager, 'sanctum')
            ->getJson('/api/manager/dashboard')
            ->assertJsonPath('rows.0.has_certificate', true)
            ->assertJsonPath('rows.0.approval_status', 'pending')
            ->assertJsonPath('rows.0.until', '2026-08-18');

        $this->actingAs($this->manager, 'sanctum')
            ->postJson("/api/leave-requests/{$created}/decide", [
                'decision' => 'approve',
                'manager_note' => 'Shërim të shpejtë.',
            ])
            ->assertOk()
            ->assertJsonPath('request.status', 'approved');

        $this->assertDatabaseHas('attendance_records', [
            'user_id' => $this->employee->id,
            'work_date' => '2026-08-17',
            'status' => 'sick_leave',
            'excused' => true,
        ]);

        // ...and the employee is told about the decision.
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->employee->id,
            'type' => 'leave_decided',
        ]);
    }

    public function test_rejecting_a_leave_removes_the_leave_status_again(): void
    {
        $this->freezeAt('08:05');

        $leave = LeaveRequest::create([
            'business_id' => $this->business->id,
            'user_id' => $this->employee->id,
            'type' => 'annual_leave',
            'start_date' => '2026-08-17',
            'end_date' => '2026-08-17',
            'status' => 'approved',
        ]);

        $this->actingAs($this->manager, 'sanctum')->getJson('/api/manager/dashboard');
        $this->assertDatabaseHas('attendance_records', ['status' => 'annual_leave']);

        $this->actingAs($this->manager, 'sanctum')
            ->postJson("/api/leave-requests/{$leave->id}/decide", ['decision' => 'reject'])
            ->assertOk();

        $record = AttendanceRecord::where('user_id', $this->employee->id)->first();
        $this->assertNotSame('annual_leave', $record->status);
        $this->assertFalse((bool) $record->excused);
    }

    public function test_a_manager_cannot_decide_on_another_teams_request(): void
    {
        $stranger = User::create([
            'business_id' => $this->business->id,
            'name' => 'Stranger', 'email' => 's@test.com', 'password' => 'password',
            'role' => 'employee', 'expected_start_time' => '08:00:00',
        ]);

        $leave = LeaveRequest::create([
            'business_id' => $this->business->id,
            'user_id' => $stranger->id,
            'type' => 'annual_leave',
            'start_date' => '2026-08-17',
            'end_date' => '2026-08-17',
            'status' => 'pending',
        ]);

        $this->actingAs($this->manager, 'sanctum')
            ->postJson("/api/leave-requests/{$leave->id}/decide", ['decision' => 'approve'])
            ->assertForbidden();
    }

    public function test_the_monthly_report_counts_late_sick_absent_and_approved_leave(): void
    {
        // Friday evening of that week: every past working day of the month is
        // covered by the rows below, so nothing extra gets backfilled.
        Carbon::setTestNow(Carbon::parse('2026-08-07 18:00'));

        $rows = [
            ['2026-08-03', 'late', 27, false],
            ['2026-08-04', 'late', 12, false],
            ['2026-08-05', 'absent', 0, false],
            ['2026-08-06', 'sick_leave', 0, true],
            ['2026-08-07', 'sick_leave', 0, true],
        ];

        foreach ($rows as [$date, $status, $late, $excused]) {
            AttendanceRecord::create([
                'business_id' => $this->business->id,
                'user_id' => $this->employee->id,
                'work_date' => $date,
                'status' => $status,
                'late_minutes' => $late,
                'excused' => $excused,
                'expected_start_time' => '08:00:00',
                'reported_at' => Carbon::parse($date . ' 08:30'),
            ]);
        }

        LeaveRequest::create([
            'business_id' => $this->business->id,
            'user_id' => $this->employee->id,
            'type' => 'annual_leave',
            'start_date' => '2026-08-10',
            'end_date' => '2026-08-11',
            'status' => 'approved',
        ]);

        $response = $this->actingAs($this->manager, 'sanctum')
            ->getJson('/api/manager/monthly-report?month=2026-08')
            ->assertOk();

        $row = collect($response->json('rows'))->firstWhere('user_id', $this->employee->id);

        $this->assertSame(2, $row['late']);
        $this->assertSame(39, $row['late_minutes']);
        $this->assertSame(2, $row['sick_days']);
        // 08-05 is the only working day of the month with no explanation.
        $this->assertSame(1, $row['absent']);
        $this->assertSame(1, $row['unexcused_absent']);
        $this->assertSame(2, $row['approved_leave_days']);
    }

    public function test_admin_can_create_and_delete_an_employee(): void
    {
        $admin = User::create([
            'business_id' => $this->business->id,
            'name' => 'Admin', 'email' => 'a@test.com', 'password' => 'password',
            'role' => 'admin', 'expected_start_time' => '08:00:00',
        ]);

        $id = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/admin/users', [
                'name' => 'New Person',
                'email' => 'new@test.com',
                'password' => 'password',
                'role' => 'employee',
                'expected_start_time' => '09:30',
                'manager_id' => $this->manager->id,
            ])
            ->assertCreated()
            ->assertJsonPath('user.expected_start_time', '09:30')
            ->json('user.id');

        $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/admin/users/{$id}")
            ->assertOk();

        $this->assertDatabaseMissing('users', ['email' => 'new@test.com']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }
}
