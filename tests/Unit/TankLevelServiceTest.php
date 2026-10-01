<?php

namespace Tests\Unit;

use App\Services\TankLevelService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TankLevelServiceTest extends TestCase
{
    #[DataProvider('levelCases')]
    public function test_it_converts_sensor_distance_and_clamps_the_result(
        float $distance,
        float $referenceHeight,
        float $expectedHeight,
        float $expectedPercent,
    ): void {
        $level = (new TankLevelService)->calculate($distance, $referenceHeight);

        $this->assertSame($expectedHeight, $level['water_height_cm']);
        $this->assertSame($expectedPercent, $level['percent']);
    }

    public static function levelCases(): array
    {
        return [
            'half-full' => [100, 200, 100.0, 50.0],
            'sensor-near-water' => [10, 200, 190.0, 95.0],
            'distance-past-bottom' => [240, 200, 0.0, 0.0],
        ];
    }

    public function test_it_returns_unknown_level_without_a_reference_height(): void
    {
        $level = (new TankLevelService)->calculate(50, null);

        $this->assertSame(50.0, $level['distance_cm']);
        $this->assertNull($level['water_height_cm']);
        $this->assertNull($level['percent']);
    }
}
