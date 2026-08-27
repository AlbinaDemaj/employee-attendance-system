<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\User;
use App\Services\NetworkGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Dy gjërat që e mbajnë produktin në këmbë si SaaS:
 *  1. asnjë biznes nuk sheh dot të dhënat e një tjetri;
 *  2. check-in-i lejohet vetëm nga rrjeti i punës.
 */
class TenancyAndNetworkTest extends TestCase
{
    use RefreshDatabase;

    private Business $alpha;
    private Business $beta;
    private User $superAdmin;
    private User $alphaAdmin;
    private User $alphaManager;
    private User $alphaEmployee;
    private User $betaAdmin;
    private User $betaEmployee;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-08-17 09:00')); // e hënë

        $this->superAdmin = User::create([
            'name' => 'Pronari', 'email' => 'super@test.com', 'password' => 'password',
            'role' => 'super_admin', 'expected_start_time' => '08:00:00',
        ]);

        $this->alpha = Business::create([
            'name' => 'Alpha', 'require_network_for_checkin' => true,
        ]);
        $this->alpha->networks()->create(['label' => 'Zyra Alpha', 'ip_range' => '10.1.0.0/16']);

        $this->beta = Business::create([
            'name' => 'Beta', 'require_network_for_checkin' => true,
        ]);
        $this->beta->networks()->create(['label' => 'Zyra Beta', 'ip_range' => '203.0.113.7']);

        $this->alphaAdmin = $this->user($this->alpha, 'admin', 'a-admin@test.com');
        $this->alphaManager = $this->user($this->alpha, 'manager', 'a-mgr@test.com');
        $this->alphaEmployee = $this->user($this->alpha, 'employee', 'a-emp@test.com', $this->alphaManager);

        $this->betaAdmin = $this->user($this->beta, 'admin', 'b-admin@test.com');
        $this->betaEmployee = $this->user($this->beta, 'employee', 'b-emp@test.com');
    }

    private function user(Business $b, string $role, string $email, ?User $manager = null): User
    {
        return User::create([
            'business_id' => $b->id,
            'name' => ucfirst($role) . ' ' . $b->name,
            'email' => $email,
            'password' => 'password',
            'role' => $role,
            'expected_start_time' => '08:00:00',
            'manager_id' => $manager?->id,
        ]);
    }

    /** Kërkesa sikur të vinte nga kjo IP. */
    private function fromIp(string $ip): array
    {
        return ['REMOTE_ADDR' => $ip];
    }

    // ------------------------------------------------------------- izolimi ---

    public function test_a_business_only_sees_its_own_employees_on_the_board(): void
    {
        $names = collect(
            $this->actingAs($this->alphaAdmin, 'sanctum')
                ->getJson('/api/manager/dashboard')
                ->assertOk()
                ->json('rows')
        )->pluck('name');

        $this->assertTrue($names->contains($this->alphaEmployee->name));
        $this->assertFalse($names->contains($this->betaEmployee->name));
    }

    public function test_an_admin_cannot_list_users_of_another_business(): void
    {
        $emails = collect(
            $this->actingAs($this->betaAdmin, 'sanctum')
                ->getJson('/api/admin/users')
                ->assertOk()
                ->json('users')
        )->pluck('email');

        $this->assertTrue($emails->contains('b-emp@test.com'));
        $this->assertFalse($emails->contains('a-emp@test.com'));
    }

    public function test_an_admin_cannot_edit_a_user_of_another_business(): void
    {
        $this->actingAs($this->betaAdmin, 'sanctum')
            ->putJson("/api/admin/users/{$this->alphaEmployee->id}", ['department' => 'Marrë'])
            ->assertForbidden();

        $this->assertNotSame('Marrë', $this->alphaEmployee->fresh()->department);
    }

    public function test_an_admin_cannot_delete_a_user_of_another_business(): void
    {
        $this->actingAs($this->betaAdmin, 'sanctum')
            ->deleteJson("/api/admin/users/{$this->alphaEmployee->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('users', ['id' => $this->alphaEmployee->id]);
    }

    public function test_an_admin_cannot_assign_a_manager_from_another_business(): void
    {
        $this->actingAs($this->betaAdmin, 'sanctum')
            ->postJson('/api/admin/users', [
                'name' => 'X',
                'email' => 'x@test.com',
                'password' => 'password',
                'role' => 'employee',
                'manager_id' => $this->alphaManager->id,
            ])
            ->assertStatus(422);
    }

    public function test_a_business_admin_cannot_create_a_super_admin(): void
    {
        $this->actingAs($this->alphaAdmin, 'sanctum')
            ->postJson('/api/admin/users', [
                'name' => 'Hack',
                'email' => 'hack@test.com',
                'password' => 'password',
                'role' => 'super_admin',
            ])
            ->assertStatus(422);
    }

    public function test_notifications_never_cross_business_boundaries(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-17 08:30')); // pas pragut

        $this->actingAs($this->alphaAdmin, 'sanctum')->getJson('/api/manager/dashboard')->assertOk();

        // Admini i Beta-s nuk merr asnjë njoftim për punonjësit e Alpha-s.
        $this->assertDatabaseMissing('notifications', [
            'user_id' => $this->betaAdmin->id,
            'subject_user_id' => $this->alphaEmployee->id,
        ]);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->alphaAdmin->id,
            'subject_user_id' => $this->alphaEmployee->id,
            'business_id' => $this->alpha->id,
        ]);
    }

    // --------------------------------------------------- roli i super-adminit

    public function test_only_the_super_admin_reaches_the_business_registry(): void
    {
        $this->actingAs($this->alphaAdmin, 'sanctum')->getJson('/api/super/businesses')->assertForbidden();
        $this->actingAs($this->alphaEmployee, 'sanctum')->getJson('/api/super/businesses')->assertForbidden();
        $this->actingAs($this->superAdmin, 'sanctum')->getJson('/api/super/businesses')->assertOk();
    }

    public function test_the_super_admin_creates_a_business_together_with_its_first_admin(): void
    {
        $this->actingAs($this->superAdmin, 'sanctum')
            ->postJson('/api/super/businesses', [
                'name' => 'Gamma sh.p.k.',
                'admin_name' => 'Gamma Admin',
                'admin_email' => 'gamma@test.com',
                'admin_password' => 'password',
            ])
            ->assertCreated()
            ->assertJsonPath('business.name', 'Gamma sh.p.k.')
            ->assertJsonPath('business.slug', 'gamma-shpk');

        $admin = User::where('email', 'gamma@test.com')->first();
        $this->assertNotNull($admin);
        $this->assertSame('admin', $admin->role);
        $this->assertSame('Gamma sh.p.k.', $admin->business->name);
    }

    public function test_a_suspended_business_cannot_log_in(): void
    {
        $this->alpha->update(['is_active' => false]);

        $this->postJson('/api/login', ['email' => 'a-emp@test.com', 'password' => 'password'])
            ->assertStatus(422);
    }

    // ------------------------------------------------------------- rrjeti ---

    public function test_checkin_is_blocked_outside_the_work_network(): void
    {
        $this->actingAs($this->alphaEmployee, 'sanctum')
            ->withServerVariables($this->fromIp('88.99.1.1'))
            ->postJson('/api/attendance/check-in')
            ->assertStatus(422)
            ->assertJsonPath('network.allowed', false);

        $this->assertDatabaseCount('attendance_records', 0);
    }

    public function test_checkin_succeeds_from_the_work_network_and_stores_the_ip(): void
    {
        $this->actingAs($this->alphaEmployee, 'sanctum')
            ->withServerVariables($this->fromIp('10.1.4.9'))
            ->postJson('/api/attendance/check-in')
            ->assertOk();

        $this->assertDatabaseHas('attendance_records', [
            'user_id' => $this->alphaEmployee->id,
            'business_id' => $this->alpha->id,
            'checkin_ip' => '10.1.4.9',
        ]);
    }

    public function test_one_business_network_does_not_open_another_business(): void
    {
        // IP-ja e Alpha-s nuk vlen për punonjësin e Beta-s.
        $this->actingAs($this->betaEmployee, 'sanctum')
            ->withServerVariables($this->fromIp('10.1.4.9'))
            ->postJson('/api/attendance/check-in')
            ->assertStatus(422);
    }

    public function test_reporting_a_reason_works_from_any_network(): void
    {
        $this->actingAs($this->alphaEmployee, 'sanctum')
            ->withServerVariables($this->fromIp('88.99.1.1'))
            ->postJson('/api/attendance/reason', ['reason_category' => 'sick'])
            ->assertOk();

        $this->assertDatabaseHas('attendance_records', [
            'user_id' => $this->alphaEmployee->id,
            'reason_category' => 'sick',
        ]);
    }

    public function test_leave_requests_work_from_any_network(): void
    {
        $this->actingAs($this->alphaEmployee, 'sanctum')
            ->withServerVariables($this->fromIp('88.99.1.1'))
            ->postJson('/api/leave-requests', [
                'type' => 'sick_leave',
                'start_date' => '2026-08-17',
                'end_date' => '2026-08-18',
                'description' => 'Jam sëmurë.',
            ])
            ->assertCreated();
    }

    public function test_the_network_check_can_be_switched_off_per_business(): void
    {
        $this->alpha->update(['require_network_for_checkin' => false]);

        $this->actingAs($this->alphaEmployee, 'sanctum')
            ->withServerVariables($this->fromIp('88.99.1.1'))
            ->postJson('/api/attendance/check-in')
            ->assertOk();
    }

    public function test_cidr_and_single_ip_matching(): void
    {
        $guard = new NetworkGuard();

        $this->assertTrue($guard->matches('192.168.1.44', '192.168.1.0/24'));
        $this->assertFalse($guard->matches('192.168.2.44', '192.168.1.0/24'));
        $this->assertTrue($guard->matches('10.9.9.9', '10.0.0.0/8'));
        $this->assertFalse($guard->matches('11.0.0.1', '10.0.0.0/8'));
        $this->assertTrue($guard->matches('88.99.12.34', '88.99.12.34'));
        $this->assertFalse($guard->matches('88.99.12.35', '88.99.12.34'));
        $this->assertTrue($guard->matches('2001:db8::5', '2001:db8::/32'));
        $this->assertFalse($guard->matches('2001:dead::5', '2001:db8::/32'));
        // IPv4 dhe IPv6 nuk përzihen
        $this->assertFalse($guard->matches('192.168.1.1', '2001:db8::/32'));

        $this->assertTrue($guard->isValidRange('192.168.1.0/24'));
        $this->assertFalse($guard->isValidRange('jo-ip'));
        $this->assertFalse($guard->isValidRange('192.168.1.0/99'));
    }

    // -------------------------------------------------- orari sipas biznesit

    public function test_each_business_keeps_its_own_working_days(): void
    {
        // Beta punon edhe të shtunën, Alpha jo.
        $this->beta->update(['working_days' => [1, 2, 3, 4, 5, 6]]);
        Carbon::setTestNow(Carbon::parse('2026-08-22 09:00')); // e shtunë

        $this->actingAs($this->alphaEmployee, 'sanctum')
            ->getJson('/api/attendance/today')
            ->assertJsonPath('is_working_day', false);

        $this->actingAs($this->betaEmployee, 'sanctum')
            ->getJson('/api/attendance/today')
            ->assertJsonPath('is_working_day', true);
    }

    public function test_the_business_grace_period_changes_when_a_checkin_counts_as_late(): void
    {
        $this->alpha->update(['late_grace_minutes' => 15, 'require_network_for_checkin' => false]);
        Carbon::setTestNow(Carbon::parse('2026-08-17 08:10')); // 10 min vonesë

        $this->actingAs($this->alphaEmployee, 'sanctum')
            ->postJson('/api/attendance/check-in')
            ->assertOk()
            ->assertJsonPath('record.status', 'present'); // brenda tolerancës
    }

    public function test_the_admin_can_change_hours_and_networks(): void
    {
        $this->actingAs($this->alphaAdmin, 'sanctum')
            ->putJson('/api/business/settings', [
                'default_start_time' => '09:30',
                'working_days' => [1, 2, 3],
                'late_grace_minutes' => 7,
                'apply_time_to_all' => true,
            ])
            ->assertOk()
            ->assertJsonPath('business.default_start_time', '09:30')
            ->assertJsonPath('business.working_days', [1, 2, 3]);

        $this->assertSame('09:30:00', $this->alphaEmployee->fresh()->expected_start_time);

        $this->actingAs($this->alphaAdmin, 'sanctum')
            ->postJson('/api/business/networks', ['label' => 'Depo', 'ip_range' => '172.16.0.0/12'])
            ->assertCreated();

        $this->actingAs($this->alphaAdmin, 'sanctum')
            ->postJson('/api/business/networks', ['label' => 'Gabim', 'ip_range' => 'jo-ip'])
            ->assertStatus(422);
    }

    public function test_a_manager_cannot_touch_business_settings(): void
    {
        $this->actingAs($this->alphaManager, 'sanctum')
            ->getJson('/api/business/settings')
            ->assertForbidden();
    }

    public function test_requiring_a_network_without_any_registered_is_refused(): void
    {
        $this->alpha->networks()->delete();
        $this->alpha->update(['require_network_for_checkin' => false]);

        $this->actingAs($this->alphaAdmin, 'sanctum')
            ->putJson('/api/business/settings', ['require_network_for_checkin' => true])
            ->assertStatus(422);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }
}
