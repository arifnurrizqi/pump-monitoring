<?php

namespace App\Services;

class TankLevelService
{
    /** @return array{distance_cm: ?float, water_height_cm: ?float, percent: ?float} */
    public function calculate(mixed $distanceCm, mixed $referenceHeightCm): array
    {
        if ($distanceCm === null || $referenceHeightCm === null || (float) $referenceHeightCm <= 0) {
            return [
                'distance_cm' => $distanceCm === null ? null : (float) $distanceCm,
                'water_height_cm' => null,
                'percent' => null,
            ];
        }

        $distance = max(0.0, (float) $distanceCm);
        $referenceHeight = (float) $referenceHeightCm;
        $waterHeight = min($referenceHeight, max(0.0, $referenceHeight - $distance));

        return [
            'distance_cm' => round($distance, 2),
            'water_height_cm' => round($waterHeight, 2),
            'percent' => round(($waterHeight / $referenceHeight) * 100, 2),
        ];
    }
}
