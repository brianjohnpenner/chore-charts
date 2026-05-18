<?php

namespace App\Support;

class ChartIcons
{
    private const ATTRS = 'viewBox="0 0 48 48" aria-hidden="true" focusable="false" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"';

    public static function svgs(): array
    {
        $a = self::ATTRS;
        return [
            'bed' => '<svg '.$a.'><path d="M7 14v24M41 22v16M7 26h34v12H7z" stroke-width="3"/><path d="M12 18h10v8H12zM7 38h34" stroke-width="3"/></svg>',
            'toothbrush' => '<svg '.$a.'><path d="M10 38 32 16" stroke-width="3.5"/><path d="m29 13 5-5 6 6-5 5zM34 8l-2-2M37 11l-2-2M40 14l-2-2" stroke-width="2.5"/></svg>',
            'laundry' => '<svg '.$a.'><path d="M13 7h22v34H13zM18 12h3M27 12h3" stroke-width="3"/><circle cx="24" cy="28" r="9" stroke-width="3"/><path d="M17 29c5-5 9 5 14 0" stroke-width="2.5"/></svg>',
            'dishes' => '<svg '.$a.'><circle cx="18" cy="28" r="9" stroke-width="3"/><circle cx="18" cy="28" r="4" stroke-width="2"/><path d="M35 10v28M30 10v10c0 3 2 5 5 5s5-2 5-5V10" stroke-width="3"/></svg>',
            'trash' => '<svg '.$a.'><path d="M14 15h20l-2 26H16zM11 15h26M19 15v-5h10v5M20 22v12M28 22v12" stroke-width="3"/></svg>',
            'backpack' => '<svg '.$a.'><path d="M16 16c0-7 16-7 16 0v25H16z" stroke-width="3"/><path d="M18 25h12a5 5 0 0 1 5 5v8H13v-8a5 5 0 0 1 5-5z" stroke-width="3"/><path d="M20 13c0-5 8-5 8 0M13 22h-3v12h3M35 22h3v12h-3" stroke-width="3"/></svg>',
            'room' => '<svg '.$a.'><path d="M9 40h30V17L24 7 9 17z" stroke-width="3"/><path d="M20 40V26h8v14M14 22h6M28 22h6" stroke-width="3"/></svg>',
            'coin' => '<svg '.$a.'><circle cx="24" cy="24" r="17" stroke-width="3"/><circle cx="24" cy="24" r="11" stroke-width="2"/><path d="M24 14v20M18 20c0-3 3-5 6-5s6 2 6 5-3 4-6 4-6 1-6 4 3 5 6 5 6-2 6-5" stroke-width="3"/></svg>',
        ];
    }

    public static function render(string $key): string
    {
        return self::svgs()[$key] ?? '';
    }

    public static function options(): array
    {
        return [
            'bed' => 'Bed',
            'toothbrush' => 'Toothbrush',
            'laundry' => 'Laundry',
            'dishes' => 'Dishes',
            'trash' => 'Trash',
            'backpack' => 'Backpack',
            'room' => 'Room',
        ];
    }
}
