<?php

namespace App\Support;

/** Llojet e kërkesave për leje. */
class LeaveTypeCatalog
{
    public const TYPES = [
        'sick_leave' => ['label' => 'Leje mjekësore', 'emoji' => '🔵', 'requires_certificate' => true],
        'annual_leave' => ['label' => 'Pushim vjetor', 'emoji' => '🟣', 'requires_certificate' => false],
        'day_off' => ['label' => 'Ditë e lirë', 'emoji' => '⚪', 'requires_certificate' => false],
        'business_trip' => ['label' => 'Udhëtim pune / jashtë zyre', 'emoji' => '🟤', 'requires_certificate' => false],
        'unpaid' => ['label' => 'Leje pa pagesë', 'emoji' => '⚫', 'requires_certificate' => false],
    ];

    public static function label(?string $key): string
    {
        return $key ? (self::TYPES[$key]['label'] ?? $key) : '—';
    }

    public static function options(): array
    {
        return collect(self::TYPES)
            ->map(fn (array $t, string $key) => [
                'value' => $key,
                'label' => $t['label'],
                'emoji' => $t['emoji'],
                'requires_certificate' => $t['requires_certificate'],
            ])
            ->values()
            ->all();
    }

    /** Etiketat shqip për statusin e aprovimit. */
    public const APPROVAL_LABELS = [
        'pending' => 'Në pritje',
        'approved' => 'Aprovuar',
        'rejected' => 'Refuzuar',
        'info_requested' => 'Kërkohet info',
    ];

    public static function approvalLabel(?string $status): ?string
    {
        return $status ? (self::APPROVAL_LABELS[$status] ?? $status) : null;
    }
}
