<?php

namespace Tests\Unit;

use App\Support\ChoreCharts\ChartData;
use PHPUnit\Framework\TestCase;

class ChartDataTest extends TestCase
{
    public function test_normalize_preserves_intentionally_empty_sections(): void
    {
        $chart = ChartData::normalize([
            'version' => 3,
            'activeChildId' => 'molly',
            'children' => [
                [
                    'id' => 'molly',
                    'childName' => 'Molly',
                    'sections' => [],
                    'weeklyChores' => ['rows' => []],
                ],
            ],
        ]);

        $this->assertSame([], $chart['children'][0]['sections']);
    }

    public function test_normalize_adds_default_sections_when_sections_are_missing(): void
    {
        $chart = ChartData::normalize([
            'version' => 3,
            'activeChildId' => 'molly',
            'children' => [
                [
                    'id' => 'molly',
                    'childName' => 'Molly',
                    'weeklyChores' => ['rows' => []],
                ],
            ],
        ]);

        $this->assertSame(['Morning', 'Daytime', 'Before Bed'], array_column($chart['children'][0]['sections'], 'name'));
    }

    public function test_normalize_clears_labels_from_empty_daily_rows(): void
    {
        $chart = ChartData::normalize([
            'version' => 3,
            'activeChildId' => 'molly',
            'children' => [
                [
                    'id' => 'molly',
                    'childName' => 'Molly',
                    'sections' => [
                        [
                            'id' => 'morning',
                            'name' => 'Morning',
                            'rows' => [
                                [
                                    'id' => 'stale-empty-row',
                                    'type' => 'empty',
                                    'label' => 'Old chore label',
                                ],
                            ],
                        ],
                    ],
                    'weeklyChores' => ['rows' => []],
                ],
            ],
        ]);

        $this->assertSame('', $chart['children'][0]['sections'][0]['rows'][0]['label']);
    }

    public function test_normalize_accepts_all_supported_icon_keys(): void
    {
        $rows = array_map(fn (string $icon): array => [
            'id' => $icon,
            'type' => 'icon',
            'label' => $icon,
            'icon' => $icon,
        ], ChartData::ICONS);

        $chart = ChartData::normalize([
            'version' => 3,
            'activeChildId' => 'molly',
            'children' => [
                [
                    'id' => 'molly',
                    'childName' => 'Molly',
                    'sections' => [
                        [
                            'id' => 'morning',
                            'name' => 'Morning',
                            'rows' => $rows,
                        ],
                    ],
                    'weeklyChores' => ['rows' => []],
                ],
            ],
        ]);

        $this->assertSame(ChartData::ICONS, array_column($chart['children'][0]['sections'][0]['rows'], 'icon'));
    }
}
