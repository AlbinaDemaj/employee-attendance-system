<?php

namespace App\Support;

/** Statuset e prezencës me ngjyrat përkatëse. */
class StatusCatalog
{
    public const STATUSES = [
        'present' => ['label' => 'Në punë', 'color' => '#22c55e', 'emoji' => '🟢'],
        'late' => ['label' => 'Vonesë', 'color' => '#eab308', 'emoji' => '🟡'],
        'absent' => ['label' => 'Mungesë', 'color' => '#ef4444', 'emoji' => '🔴'],
        'left_early' => ['label' => 'Doli herët', 'color' => '#f97316', 'emoji' => '🟠'],
        'sick_leave' => ['label' => 'Leje mjekësore', 'color' => '#3b82f6', 'emoji' => '🔵'],
        'annual_leave' => ['label' => 'Pushim vjetor', 'color' => '#a855f7', 'emoji' => '🟣'],
        'day_off' => ['label' => 'Ditë e lirë', 'color' => '#94a3b8', 'emoji' => '⚪'],
        'business_trip' => ['label' => 'Udhëtim pune / jashtë zyre', 'color' => '#92400e', 'emoji' => '🟤'],
        // nuk ruhet kurrë - vetëm llogaritet, do të thotë "turni ende s'ka nisur"
        'awaiting' => ['label' => 'Në pritje', 'color' => '#64748b', 'emoji' => '⏳'],
    ];

    public static function label(string $status): string
    {
        return self::STATUSES[$status]['label'] ?? $status;
    }

    public static function color(string $status): string
    {
        return self::STATUSES[$status]['color'] ?? '#64748b';
    }

    public static function emoji(string $status): string
    {
        return self::STATUSES[$status]['emoji'] ?? '⏺';
    }

    public static function options(): array
    {
        return collect(self::STATUSES)
            ->map(fn (array $s, string $key) => [
                'value' => $key,
                'label' => $s['label'],
                'color' => $s['color'],
                'emoji' => $s['emoji'],
            ])
            ->values()
            ->all();
    }
}
