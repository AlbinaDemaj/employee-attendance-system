<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\LeaveTypeCatalog;
use App\Support\ReasonCatalog;
use App\Support\StatusCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Këto kredenciale nuk përputhen me të dhënat tona.'],
            ]);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'email' => ['Kjo llogari është çaktivizuar.'],
            ]);
        }

        // Nese biznesi eshte pezulluar, askush prej tij nuk hyn dot.
        if ($user->business_id && ! $user->business?->is_active) {
            throw ValidationException::withMessages([
                'email' => ['Llogaria e biznesit tuaj është pezulluar. Kontaktoni administratorin.'],
            ]);
        }

        // One token per login; older tokens for this device name are replaced.
        $user->tokens()->where('name', 'spa')->delete();
        $token = $user->createToken('spa')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $this->userPayload($user),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => $this->userPayload($request->user()),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['message' => 'Dolët nga llogaria.']);
    }

    /** Static lists the frontend needs to render pickers and colours. */
    public function meta(): JsonResponse
    {
        return response()->json([
            'statuses' => StatusCatalog::options(),
            'reasons' => ReasonCatalog::options(),
            'leave_types' => LeaveTypeCatalog::options(),
        ]);
    }

    private function userPayload(User $user): array
    {
        $user->loadMissing('manager', 'business');

        return [
            'id' => $user->id,
            'business_id' => $user->business_id,
            'business_name' => $user->business?->name,
            'business' => $user->business ? [
                'id' => $user->business->id,
                'name' => $user->business->name,
                'slug' => $user->business->slug,
                'start_time' => $user->business->defaultStartShort(),
                'end_time' => $user->business->defaultEndShort(),
                'working_days' => $user->business->workingDays(),
                'require_network_for_checkin' => $user->business->requiresNetwork(),
            ] : null,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'department' => $user->department,
            'phone' => $user->phone,
            'expected_start_time' => $user->expectedStartShort(),
            'manager_id' => $user->manager_id,
            'manager_name' => $user->manager?->name,
            'is_active' => $user->is_active,
        ];
    }
}
