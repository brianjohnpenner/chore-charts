<?php

namespace App\Support\ChoreCharts;

use Illuminate\Support\Str;

class ChartData
{
    public const VERSION = 3;

    public const DAY_KEYS = ['sun', 'mon', 'tue', 'wed', 'thu', 'fri', 'sat'];

    public const ICONS = [
        'bed',
        'toothbrush',
        'laundry',
        'dishes',
        'dishwasher',
        'trash',
        'backpack',
        'room',
        'cat',
        'dog',
        'broom',
        'vacuum',
    ];

    public static function defaultChart(): array
    {
        $child = self::defaultChild('Jack', []);

        return [
            'version' => self::VERSION,
            'activeChildId' => $child['id'],
            'children' => [$child],
        ];
    }

    public static function defaultChild(string $name, array $existingIds): array
    {
        return [
            'id' => self::uniqueSlug($name, $existingIds),
            'childName' => $name,
            'orientation' => 'landscape',
            'days' => self::defaultDays(),
            'sections' => [
                [
                    'id' => 'morning',
                    'name' => 'Morning',
                    'rows' => [
                        self::row('icon', 'Make bed', 'bed'),
                        self::row('icon', 'Brush teeth', 'toothbrush'),
                        self::row('icon', 'Laundry', 'laundry'),
                        self::row('regular', 'Feed cat', 'room'),
                    ],
                ],
                [
                    'id' => 'daytime',
                    'name' => 'Daytime',
                    'rows' => [
                        self::row('regular', 'Put dishes away', 'dishes'),
                        self::row('empty'),
                    ],
                ],
                [
                    'id' => 'before-bed',
                    'name' => 'Before Bed',
                    'rows' => [
                        self::row('regular', 'Pick up room', 'room'),
                        self::row('empty'),
                    ],
                ],
            ],
            'weeklyChores' => [
                'title' => 'Weekly Chores',
                'rows' => [
                    self::weeklyRow('regular', 'Clean bedroom', true),
                    self::weeklyRow('regular', 'Put away laundry'),
                    self::weeklyRow('empty'),
                    self::weeklyRow('empty'),
                ],
            ],
        ];
    }

    public static function defaultDays(): array
    {
        return [
            ['key' => 'sun', 'label' => 'Sun', 'color' => '#ded8ef'],
            ['key' => 'mon', 'label' => 'Mon', 'color' => '#cfe0f8'],
            ['key' => 'tue', 'label' => 'Tue', 'color' => '#fde6ca'],
            ['key' => 'wed', 'label' => 'Wed', 'color' => '#f5c9cd'],
            ['key' => 'thu', 'label' => 'Thu', 'color' => '#d2e2e6'],
            ['key' => 'fri', 'label' => 'Fri', 'color' => '#dcefd7'],
            ['key' => 'sat', 'label' => 'Sat', 'color' => '#fff2c7'],
        ];
    }

    public static function row(string $type, string $label = '', string $icon = 'room', bool $paid = false): array
    {
        $type = in_array($type, ['icon', 'regular', 'empty'], true) ? $type : 'regular';

        return [
            'id' => $type.'-'.Str::random(8),
            'type' => $type,
            'label' => $type === 'empty' ? '' : $label,
            'icon' => self::validIcon($icon),
            'paid' => $paid,
            'days' => array_fill_keys(self::DAY_KEYS, true),
        ];
    }

    public static function weeklyRow(string $type, string $label = '', bool $paid = false): array
    {
        $type = $type === 'empty' ? 'empty' : 'regular';

        return [
            'id' => 'weekly-'.$type.'-'.Str::random(8),
            'type' => $type,
            'label' => $type === 'empty' ? '' : $label,
            'paid' => $paid,
        ];
    }

    public static function normalize(array $chart): array
    {
        if (! isset($chart['children']) || ! is_array($chart['children']) || count($chart['children']) === 0) {
            return self::defaultChart();
        }

        $children = [];
        $ids = [];

        foreach ($chart['children'] as $child) {
            $child = is_array($child) ? $child : [];
            $name = trim((string) ($child['childName'] ?? 'Child')) ?: 'Child';
            $id = self::uniqueSlug((string) ($child['id'] ?? $name), $ids);
            $ids[] = $id;

            $children[] = [
                'id' => $id,
                'childName' => $name,
                'orientation' => ($child['orientation'] ?? 'landscape') === 'portrait' ? 'portrait' : 'landscape',
                'days' => self::normalizeDays(is_array($child['days'] ?? null) ? $child['days'] : []),
                'sections' => self::normalizeSections(self::sourceSections($child)),
                'weeklyChores' => self::normalizeWeekly(is_array($child['weeklyChores'] ?? null) ? $child['weeklyChores'] : []),
            ];
        }

        $activeId = $chart['activeChildId'] ?? $children[0]['id'];

        return [
            'version' => self::VERSION,
            'activeChildId' => in_array($activeId, array_column($children, 'id'), true) ? $activeId : $children[0]['id'],
            'children' => $children,
        ];
    }

    public static function uniqueSlug(string $value, array $existingIds): string
    {
        $base = Str::slug($value) ?: 'child';
        $slug = $base;
        $i = 2;

        while (in_array($slug, $existingIds, true)) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }

    private static function normalizeDays(array $days): array
    {
        return array_map(function (array $default) use ($days): array {
            $incoming = collect($days)->firstWhere('key', $default['key']) ?? [];

            return [
                'key' => $default['key'],
                'label' => trim((string) ($incoming['label'] ?? $default['label'])) ?: $default['label'],
                'color' => preg_match('/^#[0-9a-fA-F]{6}$/', (string) ($incoming['color'] ?? '')) ? $incoming['color'] : $default['color'],
            ];
        }, self::defaultDays());
    }

    private static function normalizeSections(array $sections): array
    {
        return array_values(array_map(fn ($section): array => [
            'id' => (string) (($section['id'] ?? null) ?: 'section-'.Str::random(8)),
            'name' => trim((string) ($section['name'] ?? 'Section')) ?: 'Section',
            'rows' => self::normalizeRows(is_array($section['rows'] ?? null) ? $section['rows'] : []),
        ], array_filter($sections, 'is_array')));
    }

    private static function sourceSections(array $child): array
    {
        if (array_key_exists('sections', $child) && is_array($child['sections'])) {
            return $child['sections'];
        }

        return self::defaultChild('Child', [])['sections'];
    }

    private static function normalizeRows(array $rows): array
    {
        return array_values(array_map(function ($row): array {
            $row = is_array($row) ? $row : [];
            $normalized = self::row((string) ($row['type'] ?? 'regular'));
            $normalized['id'] = (string) (($row['id'] ?? null) ?: $normalized['id']);
            $normalized['label'] = $normalized['type'] === 'empty' ? '' : (string) ($row['label'] ?? '');
            $normalized['icon'] = self::validIcon((string) ($row['icon'] ?? 'room'));
            $normalized['paid'] = (bool) ($row['paid'] ?? false);
            $normalized['days'] = array_merge(
                array_fill_keys(self::DAY_KEYS, true),
                array_intersect_key((array) ($row['days'] ?? []), array_fill_keys(self::DAY_KEYS, true)),
            );

            return $normalized;
        }, $rows));
    }

    private static function normalizeWeekly(array $weekly): array
    {
        $rows = $weekly['rows'] ?? [];

        return [
            'title' => trim((string) ($weekly['title'] ?? 'Weekly Chores')) ?: 'Weekly Chores',
            'rows' => array_values(array_map(function ($row): array {
                $row = is_array($row) ? $row : [];
                $normalized = self::weeklyRow((string) ($row['type'] ?? 'regular'));
                $normalized['id'] = (string) (($row['id'] ?? null) ?: $normalized['id']);
                $normalized['label'] = $normalized['type'] === 'empty' ? '' : (string) ($row['label'] ?? '');
                $normalized['paid'] = (bool) ($row['paid'] ?? false);

                return $normalized;
            }, is_array($rows) ? $rows : [])),
        ];
    }

    private static function validIcon(string $icon): string
    {
        return in_array($icon, self::ICONS, true) ? $icon : 'room';
    }
}
