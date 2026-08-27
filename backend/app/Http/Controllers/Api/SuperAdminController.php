<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Models\Business;
use App\Models\User;
use App\Support\SqlOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Vetëm për pronarin e produktit: krijon llogaritë e bizneseve që blejnë
 * sistemin, bashkë me adminin e parë të secilit. Nga këtu e tutje, biznesi
 * menaxhon vetë menaxherët, punonjësit, orarin dhe rrjetet e veta.
 */
class SuperAdminController extends Controller
{
    public function index(): JsonResponse
    {
        $businesses = Business::query()
            ->withCount([
                'users',
                'users as managers_count' => fn ($q) => $q->where('role', 'manager'),
                'users as employees_count' => fn ($q) => $q->where('role', 'employee'),
                'networks',
            ])
            ->orderBy('name')
            ->get();

        // Sa check-in-e sot për çdo biznes - një shenjë e shpejtë aktiviteti.
        $checkinsToday = AttendanceRecord::query()
            ->whereDate('work_date', now()->toDateString())
            ->whereNotNull('checkin_time')
            ->select('business_id', DB::raw('COUNT(*) as total'))
            ->groupBy('business_id')
            ->pluck('total', 'business_id');

        return response()->json([
            'businesses' => $businesses->map(fn (Business $b) => $this->serialize($b, (int) $checkinsToday->get($b->id, 0)))->all(),
            'totals' => [
                'businesses' => $businesses->count(),
                'active' => $businesses->where('is_active', true)->count(),
                'users' => (int) $businesses->sum('users_count'),
                'checkins_today' => (int) $checkinsToday->sum(),
            ],
        ]);
    }

    /** Krijon biznesin dhe adminin e tij të parë në një hap të vetëm. */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'contact_email' => ['nullable', 'email'],
            'contact_phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],

            'admin_name' => ['required', 'string', 'max:255'],
            'admin_email' => ['required', 'email', 'unique:users,email'],
            'admin_password' => ['required', 'string', 'min:6'],
        ]);

        $business = DB::transaction(function () use ($data) {
            $business = Business::create([
                'name' => $data['name'],
                'contact_email' => $data['contact_email'] ?? null,
                'contact_phone' => $data['contact_phone'] ?? null,
                'address' => $data['address'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            User::create([
                'business_id' => $business->id,
                'name' => $data['admin_name'],
                'email' => $data['admin_email'],
                'password' => $data['admin_password'],
                'role' => 'admin',
                'expected_start_time' => $business->default_start_time,
            ]);

            return $business;
        });

        return response()->json([
            'message' => 'Biznesi "' . $business->name . '" u krijua bashkë me adminin e tij.',
            'business' => $this->serialize($business->fresh()->loadCount('users', 'networks')),
        ], 201);
    }

    public function update(Request $request, Business $business): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'contact_email' => ['nullable', 'email'],
            'contact_phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $business->update($data);

        return response()->json([
            'message' => $business->name . ' u përditësua.',
            'business' => $this->serialize($business->fresh()->loadCount('users', 'networks')),
        ]);
    }

    public function destroy(Business $business): JsonResponse
    {
        $name = $business->name;
        $business->delete(); // cascade: përdoruesit, prezenca, lejet, njoftimet

        return response()->json([
            'message' => 'Biznesi "' . $name . '" u fshi bashkë me të gjitha të dhënat e tij.',
        ]);
    }

    /** Përdoruesit e një biznesi, për mbështetje teknike. */
    public function users(Business $business): JsonResponse
    {
        $users = $business->users()
            ->with('manager:id,name')
            ->orderByRaw(SqlOrder::byValues('role', ['admin', 'manager', 'employee']))
            ->orderBy('name')
            ->get();

        return response()->json([
            'business' => $this->serialize($business->loadCount('users', 'networks')),
            'users' => $users->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'role' => $u->role,
                'department' => $u->department,
                'manager_name' => $u->manager?->name,
                'is_active' => (bool) $u->is_active,
            ])->all(),
        ]);
    }

    /** Rivendos fjalëkalimin e një admini biznesi kur e humb. */
    public function resetAdminPassword(Request $request, Business $business): JsonResponse
    {
        $data = $request->validate([
            'user_id' => ['required', Rule::exists('users', 'id')->where('business_id', $business->id)],
            'password' => ['required', 'string', 'min:6'],
        ]);

        $user = User::findOrFail($data['user_id']);
        $user->update(['password' => $data['password']]);

        return response()->json([
            'message' => 'Fjalëkalimi i ' . $user->name . ' u rivendos.',
        ]);
    }

    private function serialize(Business $b, ?int $checkinsToday = null): array
    {
        return [
            'id' => $b->id,
            'name' => $b->name,
            'slug' => $b->slug,
            'contact_email' => $b->contact_email,
            'contact_phone' => $b->contact_phone,
            'address' => $b->address,
            'notes' => $b->notes,
            'is_active' => (bool) $b->is_active,
            'start_time' => $b->defaultStartShort(),
            'end_time' => $b->defaultEndShort(),
            'working_days' => $b->workingDays(),
            'users_count' => $b->users_count ?? 0,
            'managers_count' => $b->managers_count ?? 0,
            'employees_count' => $b->employees_count ?? 0,
            'networks_count' => $b->networks_count ?? 0,
            'checkins_today' => $checkinsToday,
            'created_at' => $b->created_at?->format('Y-m-d'),
        ];
    }
}
