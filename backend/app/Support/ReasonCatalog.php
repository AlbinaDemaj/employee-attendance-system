<?php

namespace App\Support;

/**
 * Lista fikse e arsyeve që punonjësi mund të zgjedhë kur vonohet ose kur nuk
 * ka bërë check-in, plus rregulli nëse arsyeja e justifikon mungesën vetvetiu
 * apo i duhet vendim i menaxherit.
 */
class ReasonCatalog
{
    public const REASONS = [
        'sick' => ['label' => 'Sëmurë', 'emoji' => '🤒'],
        'doctor_appointment' => ['label' => 'Vizitë te mjeku', 'emoji' => '🩺'],
        'family_emergency' => ['label' => 'Emergjencë familjare', 'emoji' => '🏠'],
        'transport_problem' => ['label' => 'Problem me makinë / transport', 'emoji' => '🚗'],
        'personal_reason' => ['label' => 'Arsye personale', 'emoji' => '🙋'],
        'approved_leave' => ['label' => 'Leje e aprovuar', 'emoji' => '📄'],
        'working_remotely' => ['label' => 'Punë nga distanca', 'emoji' => '💻'],
        'business_assignment' => ['label' => 'Detyrë pune jashtë zyre', 'emoji' => '🧳'],
        'other' => ['label' => 'Tjetër', 'emoji' => '❓'],
    ];

    /** Arsyet që justifikohen pa vendim të menaxherit. */
    public const AUTO_EXCUSED = [
        'approved_leave',
        'working_remotely',
        'business_assignment',
        'doctor_appointment',
    ];

    public static function keys(): array
    {
        return array_keys(self::REASONS);
    }

    public static function label(?string $key): ?string
    {
        return $key ? (self::REASONS[$key]['label'] ?? $key) : null;
    }

    public static function isAutoExcused(?string $key): bool
    {
        return $key !== null && in_array($key, self::AUTO_EXCUSED, true);
    }

    /** Formati që përdor frontend-i për të vizatuar listën e arsyeve. */
    public static function options(): array
    {
        return collect(self::REASONS)
            ->map(fn (array $r, string $key) => [
                'value' => $key,
                'label' => $r['label'],
                'emoji' => $r['emoji'],
                'auto_excused' => in_array($key, self::AUTO_EXCUSED, true),
            ])
            ->values()
            ->all();
    }
}
