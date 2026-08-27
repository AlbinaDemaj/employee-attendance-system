<?php

namespace Database\Seeders;

use App\Models\AttendanceRecord;
use App\Models\Business;
use App\Models\LeaveRequest;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Te dhena demo per nje produkt SaaS:
 *  - 1 super-admin (pronari i produktit, pa biznes)
 *  - 2 biznese klientë, secili me adminin, menaxherin dhe punonjesit e vet
 *  - biznesi i pare e lejon check-in nga localhost (qe ta provosh menjehere)
 *  - biznesi i dyte e lejon vetem nga nje IP zyre, pra check-in-i bllokohet
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        Notification::truncate();
        AttendanceRecord::truncate();
        LeaveRequest::truncate();
        User::truncate();
        DB::table('business_networks')->truncate();
        Business::truncate();
        DB::table('personal_access_tokens')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        // ------------------------------------------------- pronari i produktit
        User::create([
            'name' => 'Pronari i Sistemit',
            'email' => 'super@demo.com',
            'password' => 'password',
            'role' => 'super_admin',
            'business_id' => null,
            'expected_start_time' => '08:00:00',
            'phone' => '+383 44 000 000',
        ]);

        $this->seedMainBusiness();
        $this->seedSecondBusiness();

        $this->command->info('Perdoruesit demo u krijuan (fjalekalimi per te gjithe: "password"):');
        $this->command->table(
            ['Biznesi', 'Roli', 'Email'],
            User::with('business')
                ->orderByRaw("FIELD(role,'super_admin','admin','manager','employee')")
                ->orderBy('business_id')
                ->get()
                ->map(fn (User $u) => [$u->business?->name ?? '— (platforma)', $u->role, $u->email])
                ->all()
        );
    }

    /** Biznesi kryesor demo - i pajisur me histori dhe kerkesa lejesh. */
    private function seedMainBusiness(): void
    {
        $business = Business::create([
            'name' => 'Teknologji Prishtina sh.p.k.',
            'contact_email' => 'kontakt@teknologji-pr.com',
            'contact_phone' => '+383 38 111 222',
            'address' => 'Rr. Nena Tereze 12, Prishtine',
            'default_start_time' => '08:00:00',
            'default_end_time' => '16:00:00',
            'working_days' => [1, 2, 3, 4, 5],
            'late_grace_minutes' => 0,
            'manager_alert_after_minutes' => 15,
            'auto_approve_sick_with_certificate' => false,
            'require_network_for_checkin' => true,
            'notes' => 'Klienti i pare demo.',
        ]);

        // Rrjetet e lejuara. Localhost dhe rrjetet private jane ketu qe check-in-i
        // te funksionoje sapo ta hapesh app-in ne kompjuterin tend ose ne telefon
        // te lidhur me te njejtin WiFi.
        $business->networks()->createMany([
            ['label' => 'Kompjuteri lokal (zhvillim)', 'ip_range' => '127.0.0.1'],
            ['label' => 'WiFi i zyres - rrjet privat', 'ip_range' => '192.168.0.0/16'],
            ['label' => 'WiFi i zyres - rrjet privat (10.x)', 'ip_range' => '10.0.0.0/8'],
        ]);

        $admin = User::create([
            'business_id' => $business->id,
            'name' => 'Admin Aliu',
            'email' => 'admin@demo.com',
            'password' => 'password',
            'role' => 'admin',
            'department' => 'Administrate',
            'expected_start_time' => '08:00:00',
            'phone' => '+383 44 000 001',
        ]);

        $manager = User::create([
            'business_id' => $business->id,
            'name' => 'Menaxher Krasniqi',
            'email' => 'manager@demo.com',
            'password' => 'password',
            'role' => 'manager',
            'department' => 'Operacione',
            'expected_start_time' => '08:00:00',
            'manager_id' => $admin->id,
            'phone' => '+383 44 000 002',
        ]);

        $employees = collect([
            ['name' => 'Ardit Berisha', 'email' => 'ardit@demo.com', 'start' => '08:00:00', 'dept' => 'Operacione'],
            ['name' => 'Besnik Gashi', 'email' => 'besnik@demo.com', 'start' => '08:00:00', 'dept' => 'Operacione'],
            ['name' => 'Drita Hoxha', 'email' => 'drita@demo.com', 'start' => '09:00:00', 'dept' => 'Mbeshtetje'],
            ['name' => 'Erion Shala', 'email' => 'erion@demo.com', 'start' => '08:30:00', 'dept' => 'Logjistike'],
        ])->map(fn (array $e, int $i) => User::create([
            'business_id' => $business->id,
            'name' => $e['name'],
            'email' => $e['email'],
            'password' => 'password',
            'role' => 'employee',
            'department' => $e['dept'],
            'expected_start_time' => $e['start'],
            'manager_id' => $manager->id,
            'phone' => '+383 44 100 00' . ($i + 1),
        ]));

        [$ardit, $besnik, $drita, $erion] = $employees->all();

        $this->seedHistory($business, [$ardit, $besnik, $drita, $erion]);
        $this->seedLeaveRequests($business, $ardit, $besnik, $drita, $manager);
    }

    /**
     * Biznesi i dyte - ekziston qe te duket izolimi mes bizneseve dhe qe ta
     * provosh bllokimin e check-in-it jashte rrjetit te punes.
     */
    private function seedSecondBusiness(): void
    {
        $business = Business::create([
            'name' => 'Market Dardania',
            'contact_email' => 'info@market-dardania.com',
            'contact_phone' => '+383 38 333 444',
            'address' => 'Lagjja Dardani, Prishtine',
            'default_start_time' => '07:00:00',
            'default_end_time' => '15:00:00',
            'working_days' => [1, 2, 3, 4, 5, 6], // punojne edhe te shtunen
            'late_grace_minutes' => 10,
            'manager_alert_after_minutes' => 20,
            'auto_approve_sick_with_certificate' => true,
            'require_network_for_checkin' => true,
            'notes' => 'Klienti i dyte demo. Check-in-i lejohet vetem nga IP-ja e marketit.',
        ]);

        // Qellimisht NUK perfshin localhost: keshtu, kur kycesh me kete biznes,
        // check-in-i bllokohet dhe shihet mesazhi i mbrojtjes se rrjetit.
        $business->networks()->create([
            'label' => 'WiFi i marketit',
            'ip_range' => '203.0.113.10',
        ]);

        $admin = User::create([
            'business_id' => $business->id,
            'name' => 'Lira Dema',
            'email' => 'admin2@demo.com',
            'password' => 'password',
            'role' => 'admin',
            'department' => 'Administrate',
            'expected_start_time' => '07:00:00',
            'phone' => '+383 44 200 001',
        ]);

        $manager = User::create([
            'business_id' => $business->id,
            'name' => 'Faton Rexha',
            'email' => 'manager2@demo.com',
            'password' => 'password',
            'role' => 'manager',
            'department' => 'Shitje',
            'expected_start_time' => '07:00:00',
            'manager_id' => $admin->id,
            'phone' => '+383 44 200 002',
        ]);

        foreach ([
            ['name' => 'Blerim Krasniqi', 'email' => 'blerim@demo.com'],
            ['name' => 'Teuta Morina', 'email' => 'teuta@demo.com'],
        ] as $i => $e) {
            User::create([
                'business_id' => $business->id,
                'name' => $e['name'],
                'email' => $e['email'],
                'password' => 'password',
                'role' => 'employee',
                'department' => 'Shitje',
                'expected_start_time' => '07:00:00',
                'manager_id' => $manager->id,
                'phone' => '+383 44 200 10' . ($i + 1),
            ]);
        }
    }

    /** Rreth tre jave histori prezence e besueshme. */
    private function seedHistory(Business $business, array $people): void
    {
        [$ardit, $besnik, $drita, $erion] = $people;
        $today = Carbon::today();

        $script = [
            [$ardit, 'late', 27, 'transport_problem', 'Bllokim trafiku ne unaze.', false, 3],
            [$ardit, 'late', 12, 'transport_problem', 'Autobusi u vonua.', false, 8],
            [$ardit, 'late', 41, 'personal_reason', null, false, 15],
            [$ardit, 'absent', 0, null, null, false, 10],
            [$besnik, 'late', 9, 'transport_problem', null, false, 2],
            [$besnik, 'late', 19, 'transport_problem', null, false, 4],
            [$besnik, 'late', 33, 'other', 'Me ka zene gjumi.', false, 9],
            [$besnik, 'late', 7, 'transport_problem', null, false, 11],
            [$besnik, 'absent', 0, null, null, false, 14],
            [$drita, 'sick_leave', 0, 'sick', 'Grip, certifikata u dorezua.', true, 7],
            [$drita, 'sick_leave', 0, 'sick', 'Grip, certifikata u dorezua.', true, 8],
            [$erion, 'business_trip', 0, 'business_assignment', 'Vizite te klienti ne Prizren.', true, 5],
            [$erion, 'left_early', 0, 'family_emergency', 'Me duhej te merrja femijen nga shkolla.', true, 6],
        ];

        foreach ($script as [$user, $status, $lateMinutes, $reason, $note, $excused, $daysAgo]) {
            $date = $today->copy()->subDays($daysAgo);
            if (! in_array($date->dayOfWeekIso, $business->workingDays(), true)) {
                $date->subDays(2);
            }

            $expected = Carbon::parse($date->format('Y-m-d') . ' ' . $user->expected_start_time);
            $away = in_array($status, ['absent', 'sick_leave'], true);

            AttendanceRecord::updateOrCreate(
                ['user_id' => $user->id, 'work_date' => $date->format('Y-m-d')],
                [
                    'business_id' => $business->id,
                    'status' => $status,
                    'expected_start_time' => $user->expected_start_time,
                    'checkin_time' => $away ? null : $expected->copy()->addMinutes($lateMinutes),
                    'checkin_ip' => $away ? null : '192.168.1.20',
                    'checkout_time' => $status === 'left_early'
                        ? $expected->copy()->addHours(4)
                        : ($away ? null : $expected->copy()->addHours(8)),
                    'late_minutes' => $lateMinutes,
                    'reason_category' => $reason,
                    'reason_note' => $note,
                    'excused' => $excused,
                    'reported_at' => $reason ? $expected->copy()->addMinutes(max($lateMinutes, 5)) : null,
                ]
            );
        }

        // Ditet e mbetura te tre javeve te fundit mbushen si "ne pune".
        foreach ($people as $user) {
            for ($i = 1; $i <= 21; $i++) {
                $date = $today->copy()->subDays($i);
                if (! in_array($date->dayOfWeekIso, $business->workingDays(), true)) {
                    continue;
                }

                $exists = AttendanceRecord::where('user_id', $user->id)
                    ->whereDate('work_date', $date->format('Y-m-d'))
                    ->exists();

                if ($exists) {
                    continue;
                }

                $expected = Carbon::parse($date->format('Y-m-d') . ' ' . $user->expected_start_time);

                AttendanceRecord::create([
                    'business_id' => $business->id,
                    'user_id' => $user->id,
                    'work_date' => $date->format('Y-m-d'),
                    'status' => 'present',
                    'expected_start_time' => $user->expected_start_time,
                    'checkin_time' => $expected->copy()->subMinutes(rand(1, 9)),
                    'checkin_ip' => '192.168.1.20',
                    'checkout_time' => $expected->copy()->addHours(8),
                    'late_minutes' => 0,
                    'excused' => true,
                ]);
            }
        }
    }

    private function seedLeaveRequests(Business $business, User $ardit, User $besnik, User $drita, User $manager): void
    {
        $today = Carbon::today();

        LeaveRequest::create([
            'business_id' => $business->id,
            'user_id' => $drita->id,
            'type' => 'sick_leave',
            'start_date' => $today->copy()->subDays(8)->format('Y-m-d'),
            'end_date' => $today->copy()->subDays(7)->format('Y-m-d'),
            'description' => 'Kam temperature dhe nuk mund te vij ne pune.',
            'certificate_path' => 'certificates/demo-certificate.png',
            'status' => 'approved',
            'decided_by' => $manager->id,
            'decided_at' => $today->copy()->subDays(8)->setTime(9, 30),
            'manager_note' => 'Sherim te shpejte.',
        ]);

        LeaveRequest::create([
            'business_id' => $business->id,
            'user_id' => $ardit->id,
            'type' => 'sick_leave',
            'start_date' => $today->format('Y-m-d'),
            'end_date' => $today->copy()->addDays(1)->format('Y-m-d'),
            'description' => 'Kam temperature dhe nuk mund te vij sot.',
            'certificate_path' => 'certificates/demo-certificate.png',
            'status' => 'pending',
        ]);

        LeaveRequest::create([
            'business_id' => $business->id,
            'user_id' => $besnik->id,
            'type' => 'annual_leave',
            'start_date' => $today->copy()->addDays(6)->format('Y-m-d'),
            'end_date' => $today->copy()->addDays(7)->format('Y-m-d'),
            'description' => 'Udhetim familjar.',
            'status' => 'pending',
        ]);

        LeaveRequest::create([
            'business_id' => $business->id,
            'user_id' => $besnik->id,
            'type' => 'annual_leave',
            'start_date' => $today->copy()->subDays(20)->format('Y-m-d'),
            'end_date' => $today->copy()->subDays(19)->format('Y-m-d'),
            'description' => 'Dy dite pushim.',
            'status' => 'approved',
            'decided_by' => $manager->id,
            'decided_at' => $today->copy()->subDays(22),
            'manager_note' => 'Aprovuar.',
        ]);
    }
}
