<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\SqlOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Vetëm për adminin e biznesit: menaxhon menaxherët dhe punonjësit e biznesit
 * të vet. Çdo query është i kufizuar te `business_id` i adminit që bën kërkesën
 * — asnjë biznes nuk sheh dot përdoruesit e një tjetri.
 */
class AdminController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $businessId = $request->user()->business_id;

        $users = User::query()
            ->where('business_id', $businessId)
            ->with('manager:id,name')
            ->withCount('subordinates')
            ->orderByRaw(SqlOrder::byValues('role', ['admin', 'manager', 'employee']))
            ->orderBy('name')
            ->get();

        return response()->json([
            'users' => $users->map(fn (User $u) => $this->serialize($u))->all(),
            'managers' => User::where('business_id', $businessId)
                ->whereIn('role', ['manager', 'admin'])
                ->orderBy('name')
                ->get(['id', 'name', 'role'])
                ->all(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $admin = $request->user();
        $data = $request->validate($this->rules($admin, null));

        $data['expected_start_time'] = $this->normalizeTime($admin, $data['expected_start_time'] ?? null);
        $data['business_id'] = $admin->business_id; // gjithmonë biznesi i adminit

        $this->assertManagerInSameBusiness($admin, $data['manager_id'] ?? null);

        $user = User::create($data);

        return response()->json([
            'message' => $user->name . ' u krijua me sukses.',
            'user' => $this->serialize($user->fresh('manager')),
        ], 201);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $admin = $request->user();
        $this->assertSameBusiness($admin, $user);

        $data = $request->validate($this->rules($admin, $user->id, false));

        if (array_key_exists('password', $data) && ! $data['password']) {
            unset($data['password']);
        }

        if (array_key_exists('expected_start_time', $data)) {
            $data['expected_start_time'] = $this->normalizeTime($admin, $data['expected_start_time']);
        }

        // Biznesi nuk ndryshohet kurrë nga ky endpoint.
        unset($data['business_id']);

        if (isset($data['role']) && $user->isAdmin() && $data['role'] !== 'admin' && $this->adminCount($admin) <= 1) {
            abort(422, 'Biznesi duhet të ketë të paktën një admin.');
        }

        if (array_key_exists('manager_id', $data)) {
            if ((int) $data['manager_id'] === $user->id) {
                abort(422, 'Një përdorues nuk mund të jetë menaxheri i vetvetes.');
            }
            $this->assertManagerInSameBusiness($admin, $data['manager_id']);
        }

        $user->update($data);

        return response()->json([
            'message' => $user->name . ' u përditësua.',
            'user' => $this->serialize($user->fresh('manager')),
        ]);
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        $admin = $request->user();
        $this->assertSameBusiness($admin, $user);

        if ($user->id === $admin->id) {
            abort(422, 'Nuk mund të fshini llogarinë tuaj.');
        }

        if ($user->isAdmin() && $this->adminCount($admin) <= 1) {
            abort(422, 'Biznesi duhet të ketë të paktën një admin.');
        }

        $user->delete();

        return response()->json(['message' => 'Përdoruesi u fshi.']);
    }

    // ----------------------------------------------------------------- ndihmës

    private function adminCount(User $admin): int
    {
        return User::where('business_id', $admin->business_id)->where('role', 'admin')->count();
    }

    private function assertSameBusiness(User $admin, User $user): void
    {
        if ($user->business_id !== $admin->business_id) {
            abort(403, 'Ky përdorues nuk i përket biznesit tuaj.');
        }
    }

    private function assertManagerInSameBusiness(User $admin, $managerId): void
    {
        if (! $managerId) {
            return;
        }

        $manager = User::find($managerId);

        if (! $manager || $manager->business_id !== $admin->business_id) {
            abort(422, 'Menaxheri i zgjedhur nuk i përket biznesit tuaj.');
        }
    }

    private function rules(User $admin, ?int $ignoreId, bool $creating = true): array
    {
        return [
            'name' => [$creating ? 'required' : 'sometimes', 'string', 'max:255'],
            'email' => [
                $creating ? 'required' : 'sometimes',
                'email',
                Rule::unique('users', 'email')->ignore($ignoreId),
            ],
            'password' => [$creating ? 'required' : 'nullable', 'string', 'min:6'],
            // Admini i biznesit nuk krijon dot super-adminë.
            'role' => [$creating ? 'required' : 'sometimes', Rule::in(['admin', 'manager', 'employee'])],
            'department' => ['nullable', 'string', 'max:255'],
            'expected_start_time' => ['nullable', 'date_format:H:i'],
            'manager_id' => ['nullable', 'exists:users,id'],
            'phone' => ['nullable', 'string', 'max:50'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /** Pa orë të dhënë, punonjësi trashëgon orarin standard të biznesit. */
    private function normalizeTime(User $admin, ?string $time): string
    {
        if ($time) {
            return $time . ':00';
        }

        return $admin->business?->default_start_time
            ?? config('attendance.default_expected_start_time') . ':00';
    }

    private function serialize(User $u): array
    {
        return [
            'id' => $u->id,
            'name' => $u->name,
            'email' => $u->email,
            'role' => $u->role,
            'department' => $u->department,
            'phone' => $u->phone,
            'expected_start_time' => $u->expectedStartShort(),
            'manager_id' => $u->manager_id,
            'manager_name' => $u->manager?->name,
            'is_active' => (bool) $u->is_active,
            'subordinates_count' => $u->subordinates_count ?? 0,
        ];
    }
}
