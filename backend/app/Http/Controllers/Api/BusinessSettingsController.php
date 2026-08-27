<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BusinessNetwork;
use App\Services\NetworkGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Admini i biznesit cakton orarin, ditët e punës, rregullat e prezencës dhe
 * rrjetet (WiFi) nga të cilat lejohet check-in-i.
 */
class BusinessSettingsController extends Controller
{
    public function __construct(private readonly NetworkGuard $network) {}

    public function show(Request $request): JsonResponse
    {
        $business = $request->user()->business;

        return response()->json([
            'business' => $this->serialize($request),
            'networks' => $business->networks()->orderBy('id')->get()->map(fn (BusinessNetwork $n) => [
                'id' => $n->id,
                'label' => $n->label,
                'ip_range' => $n->ip_range,
                'is_active' => (bool) $n->is_active,
                'matches_you' => $this->network->matches($request->ip(), $n->ip_range),
            ])->all(),
            // IP-ja nga e cila po shikon tani - e dobishme për ta shtuar me një klik.
            'your_ip' => $request->ip(),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $business = $request->user()->business;

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'contact_email' => ['nullable', 'email'],
            'contact_phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:255'],
            'default_start_time' => ['sometimes', 'date_format:H:i'],
            'default_end_time' => ['sometimes', 'date_format:H:i'],
            'working_days' => ['sometimes', 'array', 'min:1'],
            'working_days.*' => ['integer', 'between:1,7'],
            'late_grace_minutes' => ['sometimes', 'integer', 'between:0,240'],
            'manager_alert_after_minutes' => ['sometimes', 'integer', 'between:0,480'],
            'auto_approve_sick_with_certificate' => ['sometimes', 'boolean'],
            'require_network_for_checkin' => ['sometimes', 'boolean'],
            'apply_time_to_all' => ['sometimes', 'boolean'],
        ]);

        $applyToAll = (bool) ($data['apply_time_to_all'] ?? false);
        unset($data['apply_time_to_all']);

        foreach (['default_start_time', 'default_end_time'] as $field) {
            if (isset($data[$field])) {
                $data[$field] = $data[$field] . ':00';
            }
        }

        if (isset($data['working_days'])) {
            $data['working_days'] = array_values(array_unique(array_map('intval', $data['working_days'])));
            sort($data['working_days']);
        }

        // Mos e lër biznesin të kërkojë rrjet pa pasur asnjë rrjet të regjistruar -
        // përndryshe askush nuk bën dot check-in.
        if (($data['require_network_for_checkin'] ?? false) && $business->networks()->where('is_active', true)->count() === 0) {
            throw ValidationException::withMessages([
                'require_network_for_checkin' => [
                    'Shtoni së paku një rrjet të lejuar përpara se ta aktivizoni këtë kufizim.',
                ],
            ]);
        }

        $business->update($data);

        if ($applyToAll && isset($data['default_start_time'])) {
            $business->users()
                ->whereIn('role', ['manager', 'employee'])
                ->update(['expected_start_time' => $data['default_start_time']]);
        }

        return response()->json([
            'message' => 'Cilësimet u ruajtën.',
            'business' => $this->serialize($request),
        ]);
    }

    // ------------------------------------------------------------- rrjetet

    public function storeNetwork(Request $request): JsonResponse
    {
        $business = $request->user()->business;

        $data = $request->validate([
            'label' => ['required', 'string', 'max:255'],
            'ip_range' => ['required', 'string', 'max:64'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        if (! $this->network->isValidRange($data['ip_range'])) {
            throw ValidationException::withMessages([
                'ip_range' => ['Shkruani një IP të vlefshme (p.sh. 88.99.12.34) ose një rang CIDR (p.sh. 192.168.1.0/24).'],
            ]);
        }

        $network = $business->networks()->create([
            'label' => $data['label'],
            'ip_range' => trim($data['ip_range']),
            'is_active' => $data['is_active'] ?? true,
        ]);

        return response()->json([
            'message' => 'Rrjeti "' . $network->label . '" u shtua.',
            'network' => [
                'id' => $network->id,
                'label' => $network->label,
                'ip_range' => $network->ip_range,
                'is_active' => (bool) $network->is_active,
                'matches_you' => $this->network->matches($request->ip(), $network->ip_range),
            ],
        ], 201);
    }

    public function updateNetwork(Request $request, BusinessNetwork $network): JsonResponse
    {
        $this->assertOwned($request, $network);

        $data = $request->validate([
            'label' => ['sometimes', 'string', 'max:255'],
            'ip_range' => ['sometimes', 'string', 'max:64'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        if (isset($data['ip_range']) && ! $this->network->isValidRange($data['ip_range'])) {
            throw ValidationException::withMessages([
                'ip_range' => ['Shkruani një IP të vlefshme ose një rang CIDR.'],
            ]);
        }

        $network->update($data);

        return response()->json(['message' => 'Rrjeti u përditësua.']);
    }

    public function destroyNetwork(Request $request, BusinessNetwork $network): JsonResponse
    {
        $this->assertOwned($request, $network);

        $business = $request->user()->business;
        $remaining = $business->networks()->where('id', '!=', $network->id)->where('is_active', true)->count();

        if ($remaining === 0 && $business->requiresNetwork()) {
            throw ValidationException::withMessages([
                'network' => [
                    'Ky është i vetmi rrjet aktiv. Çaktivizoni fillimisht kufizimin e check-in-it, ose shtoni një rrjet tjetër.',
                ],
            ]);
        }

        $network->delete();

        return response()->json(['message' => 'Rrjeti u fshi.']);
    }

    private function assertOwned(Request $request, BusinessNetwork $network): void
    {
        if ($network->business_id !== $request->user()->business_id) {
            abort(403, 'Ky rrjet nuk i përket biznesit tuaj.');
        }
    }

    private function serialize(Request $request): array
    {
        $b = $request->user()->business->fresh();

        return [
            'id' => $b->id,
            'name' => $b->name,
            'slug' => $b->slug,
            'contact_email' => $b->contact_email,
            'contact_phone' => $b->contact_phone,
            'address' => $b->address,
            'default_start_time' => $b->defaultStartShort(),
            'default_end_time' => $b->defaultEndShort(),
            'working_days' => $b->workingDays(),
            'late_grace_minutes' => $b->lateGraceMinutes(),
            'manager_alert_after_minutes' => $b->alertAfterMinutes(),
            'auto_approve_sick_with_certificate' => $b->autoApprovesSick(),
            'require_network_for_checkin' => $b->requiresNetwork(),
            'is_active' => (bool) $b->is_active,
        ];
    }
}
