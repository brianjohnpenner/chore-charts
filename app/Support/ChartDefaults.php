<?php

namespace App\Support;

use Illuminate\Support\Str;

class ChartDefaults
{
    public const DAY_KEYS = ['sun', 'mon', 'tue', 'wed', 'thu', 'fri', 'sat'];
    public const DAY_LABELS = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
    public const DAY_COLORS = ['#ded8ef', '#cfe0f8', '#fde6ca', '#f5c9cd', '#d2e2e6', '#dcefd7', '#fff2c7'];
    public const ICONS = ['bed', 'toothbrush', 'laundry', 'dishes', 'trash', 'backpack', 'room'];
    public const ROW_TYPES = ['icon', 'regular', 'empty'];

    public static function uid(string $prefix): string
    {
        return $prefix.'-'.Str::lower(Str::random(8));
    }

    public static function slugify(string $value): string
    {
        $slug = Str::slug($value);
        return $slug !== '' ? $slug : 'child';
    }

    public static function days(): array
    {
        $out = [];
        foreach (self::DAY_KEYS as $i => $key) {
            $out[] = [
                'key' => $key,
                'label' => self::DAY_LABELS[$i],
                'color' => self::DAY_COLORS[$i],
            ];
        }
        return $out;
    }

    public static function daySelection(bool $value = true): array
    {
        $out = [];
        foreach (self::DAY_KEYS as $key) {
            $out[$key] = $value;
        }
        return $out;
    }

    public static function choreRow(string $type, string $label = '', string $icon = 'laundry', bool $paid = false): array
    {
        return [
            'id' => self::uid($type),
            'type' => $type,
            'label' => $label,
            'icon' => in_array($icon, self::ICONS, true) ? $icon : 'room',
            'paid' => $paid,
            'days' => self::daySelection(true),
        ];
    }

    public static function weeklyRow(string $type, string $label = '', bool $paid = false): array
    {
        return [
            'id' => self::uid('weekly-'.$type),
            'type' => $type,
            'label' => $label,
            'paid' => $paid,
        ];
    }

    public static function defaultSection(string $id, string $name): array
    {
        $rows = [
            self::choreRow('icon', 'Laundry', 'laundry'),
            self::choreRow('icon', 'Make bed', 'bed'),
            self::choreRow('icon', 'Brush teeth', 'toothbrush'),
        ];
        if ($name === 'Morning') {
            $rows[] = self::choreRow('regular', 'Feed cat');
        }
        return ['id' => $id, 'name' => $name, 'rows' => $rows];
    }

    public static function defaultWeekly(): array
    {
        return [
            'title' => 'Weekly Chores',
            'rows' => [
                self::weeklyRow('regular', 'Clean bedroom', true),
                self::weeklyRow('regular', 'Put away laundry'),
                self::weeklyRow('empty'),
                self::weeklyRow('empty'),
                self::weeklyRow('empty'),
            ],
        ];
    }

    public static function defaultChild(string $childName = 'Jack', array $existingIds = []): array
    {
        $id = self::slugify($childName);
        if (in_array($id, $existingIds, true)) {
            $i = 2;
            while (in_array("{$id}-{$i}", $existingIds, true)) {
                $i++;
            }
            $id = "{$id}-{$i}";
        }
        return [
            'id' => $id,
            'childName' => $childName,
            'orientation' => 'landscape',
            'days' => self::days(),
            'sections' => [
                self::defaultSection('morning', 'Morning'),
                self::defaultSection('daytime', 'Daytime'),
                self::defaultSection('before-bed', 'Before Bed'),
            ],
            'weeklyChores' => self::defaultWeekly(),
        ];
    }

    public static function defaultChart(): array
    {
        $child = self::defaultChild();
        return [
            'activeChildId' => $child['id'],
            'children' => [$child],
        ];
    }
}
