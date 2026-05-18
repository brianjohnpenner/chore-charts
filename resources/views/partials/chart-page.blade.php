@php
    $sections = $child['sections'];
    $iconGroups = function (array $section) {
        $iconRows = array_values(array_filter($section['rows'], fn ($r) => $r['type'] === 'icon'));
        $groups = [];
        for ($i = 0; $i < count($iconRows); $i += 3) {
            $groups[] = [
                'id' => $section['id'].'-icons-'.$i,
                'rows' => array_slice($iconRows, $i, 3),
            ];
        }
        return $groups;
    };
    $detailRows = fn (array $section) => array_values(array_filter($section['rows'], fn ($r) => $r['type'] !== 'icon'));
@endphp

<article class="cc-page cc-page-{{ $child['orientation'] }}">
    <h2 class="cc-page-title">{{ $child['childName'] }}'s Responsibility Chart</h2>
    <div class="cc-grid">
        @foreach ($child['days'] as $day)
            <div class="cc-day-header" style="grid-column: span 3; background-color: {{ $day['color'] }}">{{ $day['label'] }}</div>
        @endforeach

        @foreach ($sections as $section)
            <div class="cc-section-row" style="grid-column: 1 / -1">{{ $section['name'] }}</div>

            @foreach ($iconGroups($section) as $group)
                @foreach ($child['days'] as $day)
                    @for ($slot = 0; $slot < 3; $slot++)
                        @php
                            $row = $group['rows'][$slot] ?? null;
                            $visible = $row && ($row['days'][$day['key']] ?? false);
                        @endphp
                        <div class="cc-cell cc-cell-icon" style="background-color: {{ $day['color'] }}">
                            @if ($visible)
                                @if ($row['paid']) <span class="cc-coin">{!! $icons['coin'] !!}</span> @endif
                                <span class="cc-icon">{!! $icons[$row['icon']] ?? '' !!}</span>
                            @endif
                        </div>
                    @endfor
                @endforeach
            @endforeach

            @foreach ($detailRows($section) as $row)
                @foreach ($child['days'] as $day)
                    @php $visible = $row['days'][$day['key']] ?? false; @endphp
                    <div class="cc-cell {{ $row['type'] === 'empty' ? 'cc-cell-write' : 'cc-cell-text' }}" style="grid-column: span 3; background-color: {{ $day['color'] }}">
                        @if ($visible)
                            @if ($row['paid']) <span class="cc-coin">{!! $icons['coin'] !!}</span> @endif
                            @if ($row['type'] === 'regular')
                                <span>{{ $row['label'] }}</span>
                            @else
                                <span class="cc-write-line"></span>
                            @endif
                        @endif
                    </div>
                @endforeach
            @endforeach
        @endforeach

        <div class="cc-section-row cc-section-row-weekly" style="grid-column: 1 / -1">{{ $child['weeklyChores']['title'] }}</div>
        @foreach ($child['weeklyChores']['rows'] as $row)
            <div class="cc-cell cc-cell-weekly" style="grid-column: 1 / -1">
                @if ($row['paid']) <span class="cc-coin">{!! $icons['coin'] !!}</span> @endif
                @if ($row['type'] === 'regular')
                    <span>{{ $row['label'] }}</span>
                @else
                    <span class="cc-write-line"></span>
                @endif
            </div>
        @endforeach
    </div>
</article>
