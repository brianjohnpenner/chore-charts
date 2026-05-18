<?php

use App\Models\ChoreChart;
use App\Support\ChoreCharts\ChartData;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Livewire\Attributes\Url as UrlAttribute;
use Livewire\Component;

new class extends Component
{
    public ?int $chartId = null;

    public array $chart = [];

    #[UrlAttribute(as: 'view', history: true, except: 'edit')]
    public string $viewMode = 'edit';

    public string $email = '';

    public string $jsonBuffer = '';

    public ?string $notice = null;

    public ?string $error = null;

    public ?string $shareUrl = null;

    public function mount(?ChoreChart $chart = null): void
    {
        if ($chart && $chart->exists) {
            $this->chartId = $chart->id;
            $this->chart = ChartData::normalize($chart->data);
            $this->email = $chart->email ?? '';
            $this->shareUrl = URL::signedRoute('chart.show', ['chart' => $chart->public_id]);
        } else {
            $this->chart = ChartData::defaultChart();
        }

        $this->viewMode = in_array($this->viewMode, ['edit', 'preview'], true) ? $this->viewMode : 'edit';
    }

    public function updated(string $property): void
    {
        if ($this->chartId !== null && str_starts_with($property, 'chart')) {
            $this->autosave();
        }
    }

    private function autosave(): void
    {
        if ($this->chartId === null) {
            return;
        }

        ChoreChart::where('id', $this->chartId)->update([
            'title' => $this->activeChild()['childName'].' Chart',
            'data' => ChartData::normalize($this->chart),
        ]);
    }

    public function addChild(): void
    {
        $name = 'Child '.(count($this->chart['children']) + 1);
        $this->chart['children'][] = ChartData::defaultChild($name, $this->childIds());
        $this->chart['activeChildId'] = $this->chart['children'][array_key_last($this->chart['children'])]['id'];
    }

    public function duplicateChild(): void
    {
        $copy = $this->activeChild();
        $copy['childName'] .= ' Copy';
        $copy['id'] = ChartData::uniqueSlug($copy['childName'], $this->childIds());
        $this->chart['children'][] = $copy;
        $this->chart['activeChildId'] = $copy['id'];
    }

    public function deleteChild(): void
    {
        if (count($this->chart['children']) === 1) {
            return;
        }

        $activeId = $this->chart['activeChildId'];
        $this->chart['children'] = array_values(array_filter(
            $this->chart['children'],
            fn (array $child): bool => $child['id'] !== $activeId,
        ));
        $this->chart['activeChildId'] = $this->chart['children'][0]['id'];
    }

    public function addSection(): void
    {
        $childIndex = $this->activeChildIndex();
        $this->chart['children'][$childIndex]['sections'][] = [
            'id' => 'section-'.Str::random(8),
            'name' => 'New Section',
            'rows' => [],
        ];
    }

    public function deleteSection(int $sectionIndex): void
    {
        $childIndex = $this->activeChildIndex();
        unset($this->chart['children'][$childIndex]['sections'][$sectionIndex]);
        $this->chart['children'][$childIndex]['sections'] = array_values($this->chart['children'][$childIndex]['sections']);
    }

    public function addRow(int $sectionIndex, string $type): void
    {
        $childIndex = $this->activeChildIndex();
        $this->chart['children'][$childIndex]['sections'][$sectionIndex]['rows'][] = ChartData::row($type);
    }

    public function deleteRow(int $sectionIndex, int $rowIndex): void
    {
        $childIndex = $this->activeChildIndex();
        unset($this->chart['children'][$childIndex]['sections'][$sectionIndex]['rows'][$rowIndex]);
        $this->chart['children'][$childIndex]['sections'][$sectionIndex]['rows'] = array_values(
            $this->chart['children'][$childIndex]['sections'][$sectionIndex]['rows'],
        );
    }

    public function addWeeklyRow(string $type): void
    {
        $childIndex = $this->activeChildIndex();
        $this->chart['children'][$childIndex]['weeklyChores']['rows'][] = ChartData::weeklyRow($type);
    }

    public function deleteWeeklyRow(int $rowIndex): void
    {
        $childIndex = $this->activeChildIndex();
        unset($this->chart['children'][$childIndex]['weeklyChores']['rows'][$rowIndex]);
        $this->chart['children'][$childIndex]['weeklyChores']['rows'] = array_values(
            $this->chart['children'][$childIndex]['weeklyChores']['rows'],
        );
    }

    public function saveChart(): void
    {
        $this->clearMessages();

        $email = $this->normalizedEmail();

        $payload = [
            'title' => $this->activeChild()['childName'].' Chart',
            'email' => $email,
            'data' => ChartData::normalize($this->chart),
        ];

        if ($this->chartId !== null) {
            $chart = ChoreChart::find($this->chartId);

            if ($chart) {
                $chart->update($payload);
                $this->shareUrl = URL::signedRoute('chart.show', ['chart' => $chart->public_id]);
                $this->notice = 'Saved.';

                return;
            }
        }

        $chart = ChoreChart::create([
            'public_id' => ChoreChart::newPublicId(),
            ...$payload,
        ]);

        $this->chartId = $chart->id;
        $this->shareUrl = URL::signedRoute('chart.show', ['chart' => $chart->public_id]);

        $this->dispatch('chart-saved');
        $this->redirect($this->shareUrl, navigate: true);
    }

    public function emailLink(): void
    {
        $this->clearMessages();

        if ($this->shareUrl === null) {
            $this->error = 'Save the chart first, then email yourself the link.';

            return;
        }

        $validated = validator(['email' => $this->email], [
            'email' => ['required', 'email'],
        ])->validate();

        $email = strtolower($validated['email']);

        if ($this->chartId !== null) {
            ChoreChart::where('id', $this->chartId)->update(['email' => $email]);
        }

        $shareUrl = $this->shareUrl;

        Mail::raw("Open this link to view or edit your chore chart:\n\n{$shareUrl}", function (Message $message) use ($email): void {
            $message->to($email)->subject('Your chore chart link');
        });

        $this->notice = 'Link emailed. In local development, check storage/logs/laravel.log.';
    }

    public function exportJson(): void
    {
        $this->jsonBuffer = json_encode(ChartData::normalize($this->chart), JSON_PRETTY_PRINT);
    }

    public function importJson(): void
    {
        $this->clearMessages();
        $decoded = json_decode($this->jsonBuffer, true);

        if (! is_array($decoded)) {
            $this->error = 'That JSON could not be read.';

            return;
        }

        $this->chart = ChartData::normalize($decoded);
        $this->notice = 'Imported.';
    }

    public function resetChart(): void
    {
        $this->chart = ChartData::defaultChart();
        $this->notice = 'Reset to defaults.';
    }

    public function activeChild(): array
    {
        return $this->chart['children'][$this->activeChildIndex()] ?? $this->chart['children'][0];
    }

    public function iconOptions(): array
    {
        $labels = [
            'bed' => 'Bed',
            'toothbrush' => 'Toothbrush',
            'laundry' => 'Laundry',
            'dishes' => 'Dishes',
            'dishwasher' => 'Dishwasher',
            'trash' => 'Trash',
            'backpack' => 'Backpack',
            'room' => 'Room',
            'cat' => 'Cat',
            'dog' => 'Dog',
            'broom' => 'Broom',
            'vacuum' => 'Vacuum',
        ];

        return array_intersect_key($labels, array_flip(ChartData::ICONS));
    }

    public function iconLabel(string $icon): string
    {
        return $this->iconOptions()[$icon] ?? Str::headline($icon);
    }

    public function iconSvg(string $icon): string
    {
        return match ($icon) {
            'bed' => '<svg viewBox="0 0 24 24"><path d="M4 11V6a2 2 0 0 1 2-2h5a3 3 0 0 1 3 3v4"/><path d="M3 19v-8h18a2 2 0 0 1 2 2v6"/><path d="M3 16h20"/><path d="M7 19v-3"/><path d="M19 19v-3"/></svg>',
            'toothbrush' => '<svg viewBox="0 0 24 24"><path d="M4 20 15 9"/><path d="M14 6.5 17.5 3 21 6.5 17.5 10"/><path d="M15.5 4.5 19.5 8.5"/><path d="m5.5 18.5 2 2"/><path d="m12.5 9.5 2 2"/><path d="M18 3.5v2"/><path d="M20.5 6h-2"/></svg>',
            'laundry' => '<svg viewBox="0 0 24 24"><rect x="5" y="3" width="14" height="18" rx="2"/><path d="M8 7h.01"/><path d="M12 7h4"/><circle cx="12" cy="14" r="4"/><path d="M9 14c2 1.3 4 1.3 6 0"/></svg>',
            'dishes' => '<svg viewBox="0 0 24 24"><path d="M4 10a8 8 0 0 0 16 0Z"/><path d="M6 18h12"/><path d="M9 21h6"/><path d="M8 4v3"/><path d="M12 3v4"/><path d="M16 4v3"/></svg>',
            'dishwasher' => '<svg viewBox="0 0 24 24"><rect x="5" y="3" width="14" height="18" rx="2"/><path d="M5 8h14"/><path d="M8 6h.01"/><path d="M12 6h4"/><path d="M8 12h8"/><path d="M8 16h8"/><path d="M10 12v4"/><path d="M14 12v4"/></svg>',
            'trash' => '<svg viewBox="0 0 24 24"><path d="M4 7h16"/><path d="M9 7V4h6v3"/><path d="m7 7 1 14h8l1-14"/><path d="M10 11v6"/><path d="M14 11v6"/></svg>',
            'backpack' => '<svg viewBox="0 0 24 24"><path d="M8 7V6a4 4 0 0 1 8 0v1"/><rect x="5" y="7" width="14" height="14" rx="3"/><path d="M8 13h8"/><path d="M9 17h6"/><path d="M5 12H3v5h2"/><path d="M19 12h2v5h-2"/></svg>',
            'cat' => '<svg viewBox="0 0 24 24"><path d="M5 7 8 4l2 3h4l2-3 3 3v6a7 7 0 0 1-14 0Z"/><path d="M9 12h.01"/><path d="M15 12h.01"/><path d="M12 14v2"/><path d="M10 17h4"/><path d="M4 14H2"/><path d="M22 14h-2"/><path d="M4.5 17 2.5 18"/><path d="M19.5 17l2 1"/></svg>',
            'dog' => '<svg viewBox="0 0 24 24"><path d="M6 10V6l4 3h4l4-3v4"/><path d="M5 10a7 7 0 0 0 14 0"/><path d="M9 13h.01"/><path d="M15 13h.01"/><path d="M12 15v2"/><path d="M10 18h4"/><path d="M7 10 4 8v5l3-1"/><path d="m17 10 3-2v5l-3-1"/></svg>',
            'broom' => '<svg viewBox="0 0 24 24"><path d="M14 4 6 12"/><path d="m5 13 6 6"/><path d="M4 14c-1 2-1 4 0 6 2 1 4 1 6 0"/><path d="M6 16l-2 2"/><path d="M8 18l-2 2"/><path d="M12 6l6-4 2 2-4 6"/></svg>',
            'vacuum' => '<svg viewBox="0 0 24 24"><path d="M6 18h10a4 4 0 0 0 0-8H9v8"/><circle cx="7" cy="18" r="2"/><circle cx="17" cy="18" r="2"/><path d="M14 10V6a3 3 0 0 1 6 0v7"/><path d="M3 20h18"/></svg>',
            default => '<svg viewBox="0 0 24 24"><path d="M3 11 12 4l9 7"/><path d="M5 10v10h14V10"/><path d="M9 20v-6h6v6"/></svg>',
        };
    }

    public function activeChildIndex(): int
    {
        foreach ($this->chart['children'] as $index => $child) {
            if ($child['id'] === $this->chart['activeChildId']) {
                return $index;
            }
        }

        return 0;
    }

    private function childIds(): array
    {
        return array_column($this->chart['children'] ?? [], 'id');
    }

    private function clearMessages(): void
    {
        $this->notice = null;
        $this->error = null;
    }

    private function normalizedEmail(): ?string
    {
        $email = trim($this->email);

        return $email === '' ? null : strtolower($email);
    }
};
?>

<div
    class="app-shell"
    x-data="{
        isNew: @js($chartId === null),
        draftKey: 'chore_chart_draft',
        syncing: false,
    }"
    x-init="
        if (isNew) {
            const raw = localStorage.getItem(draftKey);
            if (raw) {
                try {
                    const parsed = JSON.parse(raw);
                    if (parsed && Array.isArray(parsed.children)) {
                        syncing = true;
                        Promise.resolve($wire.set('chart', parsed)).finally(() => { syncing = false; });
                    }
                } catch (e) {
                    localStorage.removeItem(draftKey);
                }
            }
            $wire.$watch('chart', (value) => {
                if (!isNew || syncing) return;
                try { localStorage.setItem(draftKey, JSON.stringify(value)); } catch (e) {}
            });
        }
    "
    @chart-saved.window="localStorage.removeItem(draftKey); isNew = false;"
>
    <header class="topbar no-print">
        <div>
            <h1>Chore Charts</h1>
            <p>Build the chart, print it, and save to get a link you can come back to.</p>
        </div>

        <div class="topbar-actions">
            <div class="mode-toggle" role="group" aria-label="View mode">
                <button type="button" class="{{ $viewMode === 'edit' ? 'active' : '' }}" wire:click="$set('viewMode', 'edit')">Editor</button>
                <button type="button" class="{{ $viewMode === 'preview' ? 'active' : '' }}" wire:click="$set('viewMode', 'preview')">Print View</button>
            </div>
            <button type="button" class="primary" onclick="window.print()">Print</button>
            <a class="topbar-link" href="{{ route('privacy') }}">Privacy</a>
        </div>
    </header>

    @if ($notice || $error)
        <div class="status-line no-print {{ $error ? 'error' : '' }}">
            {{ $error ?: $notice }}
        </div>
    @endif

    <section class="save-strip no-print">
        @if ($shareUrl)
            <div>
                <strong>Saved. Changes autosave.</strong>
                <span>Bookmark this URL — it's the only way back to this chart.</span>
                <input type="text" readonly class="share-url" value="{{ $shareUrl }}" onclick="this.select()">
            </div>
            <div class="magic-form">
                <input type="email" wire:model="email" placeholder="you@example.com">
                <button type="button" wire:click="emailLink">Email Link</button>
            </div>
            @error('email') <span class="form-error">{{ $message }}</span> @enderror
        @else
            <div>
                <strong>Want to save this chart?</strong>
                <span>Save it to get a shareable link. Add an email if you'd like a copy of the link.</span>
            </div>
            <div class="magic-form">
                <input type="email" wire:model="email" placeholder="you@example.com (optional)">
                <button type="button" class="primary" wire:click="saveChart">Save Chart</button>
            </div>
        @endif
    </section>

    @php
        $child = $this->activeChild();
    @endphp

    <main class="workspace {{ $viewMode === 'preview' ? 'preview-only' : '' }}">
        <section class="editor no-print {{ $viewMode !== 'edit' ? 'hidden-mode' : '' }}">
            <div class="panel compact-grid">
                <label>
                    <span>Child</span>
                    <select wire:model.live="chart.activeChildId">
                        @foreach ($chart['children'] as $savedChild)
                            <option value="{{ $savedChild['id'] }}">{{ $savedChild['childName'] }}</option>
                        @endforeach
                    </select>
                </label>

                <label>
                    <span>Name</span>
                    <input type="text" wire:model.live="chart.children.{{ $this->activeChildIndex() }}.childName">
                </label>

                <label>
                    <span>Orientation</span>
                    <select wire:model.live="chart.children.{{ $this->activeChildIndex() }}.orientation">
                        <option value="landscape">Landscape</option>
                        <option value="portrait">Portrait</option>
                    </select>
                </label>

                <div class="button-row">
                    <button type="button" wire:click="addChild">Add Child</button>
                    <button type="button" wire:click="duplicateChild">Duplicate</button>
                    <button type="button" wire:click="deleteChild" @disabled(count($chart['children']) === 1)>Delete</button>
                </div>
            </div>

            <div class="panel">
                <h2>Days</h2>
                <div class="days-editor">
                    @foreach ($child['days'] as $dayIndex => $day)
                        <label>
                            <span>{{ $day['key'] }}</span>
                            <input type="text" wire:model.live="chart.children.{{ $this->activeChildIndex() }}.days.{{ $dayIndex }}.label">
                            <input type="color" wire:model.live="chart.children.{{ $this->activeChildIndex() }}.days.{{ $dayIndex }}.color">
                        </label>
                    @endforeach
                </div>
            </div>

            @foreach ($child['sections'] as $sectionIndex => $section)
                <section class="panel section-editor" wire:key="section-editor-{{ $section['id'] }}">
                    <div class="section-heading">
                        <input type="text" wire:model.live="chart.children.{{ $this->activeChildIndex() }}.sections.{{ $sectionIndex }}.name">
                        <div class="button-row">
                            <button type="button" wire:click="addRow({{ $sectionIndex }}, 'icon')">Add Icon</button>
                            <button type="button" wire:click="addRow({{ $sectionIndex }}, 'regular')">Add Chore</button>
                            <button type="button" wire:click="addRow({{ $sectionIndex }}, 'empty')">Add Empty</button>
                            <button type="button" class="danger" wire:click="deleteSection({{ $sectionIndex }})">Delete</button>
                        </div>
                    </div>

                    <div class="row-grid header">
                        <span>Type</span>
                        <span>Chore</span>
                        @foreach ($child['days'] as $day)
                            <span>{{ $day['label'] }}</span>
                        @endforeach
                        <span>Paid</span>
                        <span></span>
                    </div>

                    @foreach ($section['rows'] as $rowIndex => $row)
                        <div class="row-grid" wire:key="row-editor-{{ $row['id'] }}">
                            <select wire:model.live="chart.children.{{ $this->activeChildIndex() }}.sections.{{ $sectionIndex }}.rows.{{ $rowIndex }}.type">
                                <option value="icon">Icon</option>
                                <option value="regular">Regular</option>
                                <option value="empty">Empty</option>
                            </select>

                            <div class="chore-fields">
                                @if ($row['type'] === 'icon')
                                    <details class="icon-picker">
                                        <summary>
                                            <span class="icon-picker-symbol svg-icon">{!! $this->iconSvg($row['icon'] ?? 'room') !!}</span>
                                            <span>{{ $this->iconLabel($row['icon'] ?? 'room') }}</span>
                                        </summary>
                                        <div class="icon-picker-menu">
                                            @foreach ($this->iconOptions() as $icon => $label)
                                                <button
                                                    type="button"
                                                    class="{{ ($row['icon'] ?? 'room') === $icon ? 'selected' : '' }}"
                                                    wire:click="$set('chart.children.{{ $this->activeChildIndex() }}.sections.{{ $sectionIndex }}.rows.{{ $rowIndex }}.icon', '{{ $icon }}')"
                                                >
                                                    <span class="icon-picker-symbol svg-icon">{!! $this->iconSvg($icon) !!}</span>
                                                    <span>{{ $label }}</span>
                                                </button>
                                            @endforeach
                                        </div>
                                    </details>
                                @endif

                                @if ($row['type'] === 'empty')
                                    <span class="empty-label">Empty box</span>
                                @else
                                    <input type="text" wire:model.live="chart.children.{{ $this->activeChildIndex() }}.sections.{{ $sectionIndex }}.rows.{{ $rowIndex }}.label" placeholder="Chore label">
                                @endif
                            </div>

                            @foreach ($child['days'] as $day)
                                <label class="day-check">
                                    <input type="checkbox" wire:model.live="chart.children.{{ $this->activeChildIndex() }}.sections.{{ $sectionIndex }}.rows.{{ $rowIndex }}.days.{{ $day['key'] }}">
                                </label>
                            @endforeach

                            <label class="paid-check">
                                <input type="checkbox" wire:model.live="chart.children.{{ $this->activeChildIndex() }}.sections.{{ $sectionIndex }}.rows.{{ $rowIndex }}.paid">
                                <span>$</span>
                            </label>

                            <button type="button" class="danger" wire:click="deleteRow({{ $sectionIndex }}, {{ $rowIndex }})">Delete</button>
                        </div>
                    @endforeach
                </section>
            @endforeach

            <div class="add-section">
                <button type="button" wire:click="addSection">Add Section</button>
            </div>

            <section class="panel section-editor">
                <div class="section-heading">
                    <input type="text" wire:model.live="chart.children.{{ $this->activeChildIndex() }}.weeklyChores.title">
                    <div class="button-row">
                        <button type="button" wire:click="addWeeklyRow('regular')">Add Weekly Chore</button>
                        <button type="button" wire:click="addWeeklyRow('empty')">Add Empty</button>
                    </div>
                </div>

                @foreach ($child['weeklyChores']['rows'] as $rowIndex => $row)
                    <div class="weekly-editor-row" wire:key="weekly-editor-{{ $row['id'] }}">
                        <select wire:model.live="chart.children.{{ $this->activeChildIndex() }}.weeklyChores.rows.{{ $rowIndex }}.type">
                            <option value="regular">Regular</option>
                            <option value="empty">Empty</option>
                        </select>
                        @if ($row['type'] === 'empty')
                            <span class="empty-label">Empty box</span>
                        @else
                            <input type="text" wire:model.live="chart.children.{{ $this->activeChildIndex() }}.weeklyChores.rows.{{ $rowIndex }}.label">
                        @endif
                        <label class="paid-check">
                            <input type="checkbox" wire:model.live="chart.children.{{ $this->activeChildIndex() }}.weeklyChores.rows.{{ $rowIndex }}.paid">
                            <span>$</span>
                        </label>
                        <button type="button" class="danger" wire:click="deleteWeeklyRow({{ $rowIndex }})">Delete</button>
                    </div>
                @endforeach
            </section>

            <section class="panel">
                <h2>Data</h2>
                <div class="button-row">
                    <button type="button" wire:click="exportJson">Export JSON</button>
                    <button type="button" wire:click="importJson">Import JSON</button>
                    <button type="button" wire:click="resetChart">Reset</button>
                </div>
                <textarea wire:model.live="jsonBuffer" rows="8" placeholder="Exported or imported chart JSON"></textarea>
            </section>
        </section>

        <section class="preview-wrap {{ $viewMode !== 'preview' ? 'hidden-mode' : '' }}">
            <article class="chart-preview {{ $child['orientation'] }}" id="printable-chart">
                @php
                    $iconLegendRows = [];
                    $seenIconLegendRows = [];

                    foreach ($child['sections'] as $section) {
                        foreach ($section['rows'] as $row) {
                            if ($row['type'] !== 'icon') {
                                continue;
                            }

                            $label = trim((string) ($row['label'] ?? ''));

                            if ($label === '') {
                                continue;
                            }

                            $legendKey = ($row['icon'] ?? 'room').'|'.$label;

                            if (isset($seenIconLegendRows[$legendKey])) {
                                continue;
                            }

                            $seenIconLegendRows[$legendKey] = true;
                            $iconLegendRows[] = $row;
                        }
                    }
                @endphp

                <h2>{{ $child['childName'] }}'s Responsibility Chart</h2>

                <div class="chart-grid">
                    @foreach ($child['days'] as $day)
                        <div class="day-header" style="grid-column: span 3; background-color: {{ $day['color'] }}">
                            {{ $day['label'] }}
                        </div>
                    @endforeach

                    @foreach ($child['sections'] as $section)
                        @php
                            $iconRows = [];
                            $detailRows = [];

                            foreach ($section['rows'] as $sectionRow) {
                                if ($sectionRow['type'] === 'icon') {
                                    $iconRows[] = $sectionRow;
                                } else {
                                    $detailRows[] = $sectionRow;
                                }
                            }

                            $iconGroups = array_chunk($iconRows, 3);
                        @endphp

                        <div class="section-row" style="grid-column: 1 / -1">{{ $section['name'] }}</div>

                        @foreach ($iconGroups as $iconGroup)
                            @foreach ($child['days'] as $day)
                                @for ($slot = 0; $slot < 3; $slot++)
                                    @php
                                        $row = $iconGroup[$slot] ?? null;
                                        $visible = $row && ($row['days'][$day['key']] ?? false);
                                    @endphp
                                    <div class="chart-cell icon-cell" style="background-color: {{ $day['color'] }}">
                                        @if ($visible)
                                            @if ($row['paid'])
                                                <span class="paid-dot">$</span>
                                            @endif
                                            <span class="icon-render svg-icon">{!! $this->iconSvg($row['icon'] ?? 'room') !!}</span>
                                        @endif
                                    </div>
                                @endfor
                            @endforeach
                        @endforeach

                        @foreach ($detailRows as $row)
                            @foreach ($child['days'] as $day)
                                @php($visible = $row['days'][$day['key']] ?? false)
                                <div class="chart-cell {{ $row['type'] === 'empty' ? 'write-cell' : 'text-cell' }}" style="grid-column: span 3; background-color: {{ $day['color'] }}">
                                    @if ($visible && $row['paid'])
                                        <span class="paid-dot">$</span>
                                    @endif
                                    @if ($visible && $row['type'] === 'regular')
                                        <span>{{ $row['label'] }}</span>
                                    @endif
                                </div>
                            @endforeach
                        @endforeach
                    @endforeach

                    <div class="section-row weekly-title" style="grid-column: 1 / -1">{{ $child['weeklyChores']['title'] }}</div>
                    <div class="weekly-chores-grid">
                        @foreach ($child['weeklyChores']['rows'] as $row)
                            <div class="chart-cell weekly-cell">
                                @if ($row['paid'])
                                    <span class="paid-dot">$</span>
                                @endif
                                @if ($row['type'] === 'empty')
                                @else
                                    <span>{{ $row['label'] }}</span>
                                @endif
                            </div>
                        @endforeach
                    </div>

                    @if ($iconLegendRows !== [])
                        <div class="section-row icon-legend-title" style="grid-column: 1 / -1">Icon Chores</div>
                        <div class="icon-legend">
                            @foreach ($iconLegendRows as $row)
                                <div class="icon-legend-item">
                                    <span class="icon-legend-symbol svg-icon">{!! $this->iconSvg($row['icon'] ?? 'room') !!}</span>
                                    <span>{{ $row['label'] }}</span>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </article>
        </section>
    </main>
</div>
