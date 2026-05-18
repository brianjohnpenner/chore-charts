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

@php
    $childIndex = $this->activeChildIndex();
    $rowGridCols = '6rem minmax(14rem,1fr) repeat(7,2.55rem) 3rem 4.5rem';
    $weeklyRowGridCols = '8rem minmax(12rem,1fr) 4rem 4.5rem';
@endphp

<div
    class="min-h-screen"
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
    <header class="no-print container-app mt-5 flex flex-wrap items-center justify-between gap-4 rounded-lg border border-slate-200 bg-white px-5 py-4 shadow-sm">
        <div>
            <h1 class="m-0 mb-1 text-2xl font-extrabold leading-tight">Chore Charts</h1>
            <p class="m-0 text-sm text-slate-500">Build the chart, print it, and save to get a link you can come back to.</p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <div class="grid grid-cols-2 gap-0.5 rounded-md border border-slate-300 bg-slate-200 p-0.5" role="group" aria-label="View mode">
                <button type="button" class="btn border-0 rounded-md min-w-[6.5rem] {{ $viewMode === 'edit' ? 'btn-active' : 'bg-transparent text-slate-600 hover:bg-transparent' }}" wire:click="$set('viewMode', 'edit')">Editor</button>
                <button type="button" class="btn border-0 rounded-md min-w-[6.5rem] {{ $viewMode === 'preview' ? 'btn-active' : 'bg-transparent text-slate-600 hover:bg-transparent' }}" wire:click="$set('viewMode', 'preview')">Print View</button>
            </div>
            <button type="button" class="btn btn-primary" onclick="window.print()">Print</button>
            <a class="btn no-underline" href="{{ route('privacy') }}">Privacy</a>
        </div>
    </header>

    @if ($notice || $error)
        <div class="no-print container-app mt-5 rounded-lg border px-5 py-3 font-bold {{ $error ? 'border-rose-300 bg-rose-100 text-rose-800' : 'border-emerald-300 bg-emerald-100 text-emerald-900' }}">
            {{ $error ?: $notice }}
        </div>
    @endif

    <section class="no-print container-app mt-5 flex flex-wrap items-center justify-between gap-4 rounded-lg border border-slate-200 bg-white px-5 py-4 shadow-sm">
        @if ($shareUrl)
            <div class="min-w-0 flex-1">
                <strong class="block">Saved. Changes autosave.</strong>
                <span class="block text-sm text-slate-500">Bookmark this URL — it's the only way back to this chart.</span>
                <input type="text" readonly class="field mt-2" value="{{ $shareUrl }}" onclick="this.select()">
            </div>
            <div class="flex flex-wrap items-center gap-2 min-w-[min(26rem,100%)]">
                <input type="email" class="field flex-1" wire:model="email" placeholder="you@example.com">
                <button type="button" class="btn" wire:click="emailLink">Email Link</button>
            </div>
            @error('email') <span class="inline-flex rounded-md bg-rose-100 px-2 py-1 text-sm font-bold text-rose-800">{{ $message }}</span> @enderror
        @else
            <div class="min-w-0 flex-1">
                <strong class="block">Want to save this chart?</strong>
                <span class="block text-sm text-slate-500">Save it to get a shareable link. Add an email if you'd like a copy of the link.</span>
            </div>
            <div class="flex flex-wrap items-center gap-2 min-w-[min(26rem,100%)]">
                <input type="email" class="field flex-1" wire:model="email" placeholder="you@example.com (optional)">
                <button type="button" class="btn btn-primary" wire:click="saveChart">Save Chart</button>
            </div>
        @endif
    </section>

    @php
        $child = $this->activeChild();
    @endphp

    <main class="container-app mt-5 grid gap-5 pb-5 {{ $viewMode === 'preview' ? 'grid-cols-1' : 'grid-cols-1 xl:grid-cols-[minmax(42rem,1.12fr)_minmax(38rem,.88fr)]' }}">
        <section class="no-print grid gap-4 {{ $viewMode !== 'edit' ? 'hidden-mode' : '' }}">
            <div class="panel grid grid-cols-1 gap-3 md:grid-cols-[repeat(3,minmax(10rem,1fr))_auto]">
                <label>
                    <span class="panel-label">Child</span>
                    <select class="field" wire:model.live="chart.activeChildId">
                        @foreach ($chart['children'] as $savedChild)
                            <option value="{{ $savedChild['id'] }}">{{ $savedChild['childName'] }}</option>
                        @endforeach
                    </select>
                </label>

                <label>
                    <span class="panel-label">Name</span>
                    <input type="text" class="field" wire:model.live="chart.children.{{ $childIndex }}.childName">
                </label>

                <label>
                    <span class="panel-label">Orientation</span>
                    <select class="field" wire:model.live="chart.children.{{ $childIndex }}.orientation">
                        <option value="landscape">Landscape</option>
                        <option value="portrait">Portrait</option>
                    </select>
                </label>

                <div class="flex flex-wrap items-center gap-2">
                    <button type="button" class="btn" wire:click="addChild">Add Child</button>
                    <button type="button" class="btn" wire:click="duplicateChild">Duplicate</button>
                    <button type="button" class="btn" wire:click="deleteChild" @disabled(count($chart['children']) === 1)>Delete</button>
                </div>
            </div>

            <div class="panel">
                <h2 class="panel-label mb-3 text-base">Days</h2>
                <div class="grid gap-2.5 grid-cols-[repeat(auto-fit,minmax(7rem,1fr))]">
                    @foreach ($child['days'] as $dayIndex => $day)
                        <label class="grid gap-1.5">
                            <span class="panel-label">{{ $day['key'] }}</span>
                            <input type="text" class="field" wire:model.live="chart.children.{{ $childIndex }}.days.{{ $dayIndex }}.label">
                            <input type="color" class="field h-[2.2rem] p-0.5" wire:model.live="chart.children.{{ $childIndex }}.days.{{ $dayIndex }}.color">
                        </label>
                    @endforeach
                </div>
            </div>

            @foreach ($child['sections'] as $sectionIndex => $section)
                <section class="panel" wire:key="section-editor-{{ $section['id'] }}">
                    <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
                        <input type="text" class="field flex-1 min-w-[14rem] font-extrabold" wire:model.live="chart.children.{{ $childIndex }}.sections.{{ $sectionIndex }}.name">
                        <div class="flex flex-wrap items-center gap-2">
                            <button type="button" class="btn" wire:click="addRow({{ $sectionIndex }}, 'icon')">Add Icon</button>
                            <button type="button" class="btn" wire:click="addRow({{ $sectionIndex }}, 'regular')">Add Chore</button>
                            <button type="button" class="btn" wire:click="addRow({{ $sectionIndex }}, 'empty')">Add Empty</button>
                            <button type="button" class="btn btn-danger" wire:click="deleteSection({{ $sectionIndex }})">Delete</button>
                        </div>
                    </div>

                    <div class="grid items-center gap-1.5 text-center text-xs font-extrabold uppercase text-slate-500" style="grid-template-columns: {{ $rowGridCols }}">
                        <span class="text-left">Type</span>
                        <span class="text-left">Chore</span>
                        @foreach ($child['days'] as $day)
                            <span>{{ $day['label'] }}</span>
                        @endforeach
                        <span>Paid</span>
                        <span></span>
                    </div>

                    @foreach ($section['rows'] as $rowIndex => $row)
                        <div class="relative grid items-center gap-1.5 border-t border-slate-100 min-h-[3.1rem] py-1.5 has-[.icon-picker[open]]:z-40" wire:key="row-editor-{{ $row['id'] }}" style="grid-template-columns: {{ $rowGridCols }}">
                            <select class="field" wire:model.live="chart.children.{{ $childIndex }}.sections.{{ $sectionIndex }}.rows.{{ $rowIndex }}.type">
                                <option value="icon">Icon</option>
                                <option value="regular">Regular</option>
                                <option value="empty">Empty</option>
                            </select>

                            <div class="grid items-center gap-1.5 grid-cols-[minmax(0,auto)_minmax(8rem,1fr)]">
                                @if ($row['type'] === 'icon')
                                    <details class="icon-picker relative min-w-[9.5rem]">
                                        <summary class="flex cursor-pointer list-none items-center gap-1.5 rounded-md border border-slate-300 bg-white px-2.5 py-1.5 font-bold min-h-[2.35rem]">
                                            <span class="icon-picker-symbol inline-flex flex-none items-center justify-center">{!! $this->iconSvg($row['icon'] ?? 'room') !!}</span>
                                            <span>{{ $this->iconLabel($row['icon'] ?? 'room') }}</span>
                                            <span class="ml-auto inline-block h-0 w-0 border-x-[0.25rem] border-t-[0.32rem] border-x-transparent border-t-slate-500"></span>
                                        </summary>
                                        <div class="absolute left-0 top-full mt-1 z-20 grid gap-1 rounded-lg border border-slate-300 bg-white p-1.5 shadow-lg min-w-[13rem]">
                                            @foreach ($this->iconOptions() as $icon => $label)
                                                <button
                                                    type="button"
                                                    class="inline-flex items-center justify-start gap-1.5 rounded-md border-0 px-2 py-1.5 min-h-[2.1rem] font-bold hover:bg-slate-100 {{ ($row['icon'] ?? 'room') === $icon ? 'bg-slate-200' : 'bg-white' }}"
                                                    wire:click="$set('chart.children.{{ $childIndex }}.sections.{{ $sectionIndex }}.rows.{{ $rowIndex }}.icon', '{{ $icon }}')"
                                                >
                                                    <span class="icon-picker-symbol inline-flex flex-none items-center justify-center">{!! $this->iconSvg($icon) !!}</span>
                                                    <span>{{ $label }}</span>
                                                </button>
                                            @endforeach
                                        </div>
                                    </details>
                                @endif

                                @if ($row['type'] === 'empty')
                                    <span class="font-bold text-slate-500 {{ ! isset($row['type']) || $row['type'] === 'empty' ? 'col-span-full' : '' }}">Empty box</span>
                                @else
                                    <input type="text" class="field {{ $row['type'] !== 'icon' ? 'col-span-full' : '' }}" wire:model.live="chart.children.{{ $childIndex }}.sections.{{ $sectionIndex }}.rows.{{ $rowIndex }}.label" placeholder="Chore label">
                                @endif
                            </div>

                            @foreach ($child['days'] as $day)
                                <label class="flex items-center justify-center">
                                    <input type="checkbox" class="h-4 w-4" wire:model.live="chart.children.{{ $childIndex }}.sections.{{ $sectionIndex }}.rows.{{ $rowIndex }}.days.{{ $day['key'] }}">
                                </label>
                            @endforeach

                            <label class="flex items-center justify-center gap-1">
                                <input type="checkbox" class="h-4 w-4" wire:model.live="chart.children.{{ $childIndex }}.sections.{{ $sectionIndex }}.rows.{{ $rowIndex }}.paid">
                                <span class="paid-pill">$</span>
                            </label>

                            <button type="button" class="btn btn-danger" wire:click="deleteRow({{ $sectionIndex }}, {{ $rowIndex }})">Delete</button>
                        </div>
                    @endforeach
                </section>
            @endforeach

            <div class="flex justify-center">
                <button type="button" class="btn" wire:click="addSection">Add Section</button>
            </div>

            <section class="panel">
                <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
                    <input type="text" class="field flex-1 min-w-[14rem] font-extrabold" wire:model.live="chart.children.{{ $childIndex }}.weeklyChores.title">
                    <div class="flex flex-wrap items-center gap-2">
                        <button type="button" class="btn" wire:click="addWeeklyRow('regular')">Add Weekly Chore</button>
                        <button type="button" class="btn" wire:click="addWeeklyRow('empty')">Add Empty</button>
                    </div>
                </div>

                @foreach ($child['weeklyChores']['rows'] as $rowIndex => $row)
                    <div class="grid items-center gap-2 border-t border-slate-100 py-2" wire:key="weekly-editor-{{ $row['id'] }}" style="grid-template-columns: {{ $weeklyRowGridCols }}">
                        <select class="field" wire:model.live="chart.children.{{ $childIndex }}.weeklyChores.rows.{{ $rowIndex }}.type">
                            <option value="regular">Regular</option>
                            <option value="empty">Empty</option>
                        </select>
                        @if ($row['type'] === 'empty')
                            <span class="font-bold text-slate-500">Empty box</span>
                        @else
                            <input type="text" class="field" wire:model.live="chart.children.{{ $childIndex }}.weeklyChores.rows.{{ $rowIndex }}.label">
                        @endif
                        <label class="flex items-center justify-center gap-1">
                            <input type="checkbox" class="h-4 w-4" wire:model.live="chart.children.{{ $childIndex }}.weeklyChores.rows.{{ $rowIndex }}.paid">
                            <span class="paid-pill">$</span>
                        </label>
                        <button type="button" class="btn btn-danger" wire:click="deleteWeeklyRow({{ $rowIndex }})">Delete</button>
                    </div>
                @endforeach
            </section>

            <section class="panel">
                <h2 class="panel-label mb-3 text-base">Data</h2>
                <div class="mb-2 flex flex-wrap items-center gap-2">
                    <button type="button" class="btn" wire:click="exportJson">Export JSON</button>
                    <button type="button" class="btn" wire:click="importJson">Import JSON</button>
                    <button type="button" class="btn" wire:click="resetChart">Reset</button>
                </div>
                <textarea class="field min-h-[10rem] resize-y" wire:model.live="jsonBuffer" rows="8" placeholder="Exported or imported chart JSON"></textarea>
            </section>
        </section>

        <section class="overflow-auto {{ $viewMode !== 'preview' ? 'hidden-mode' : '' }} {{ $viewMode === 'preview' ? 'max-w-[88rem] mx-auto' : '' }}">
            <article class="chart-preview {{ $child['orientation'] }} mx-auto border border-slate-300 bg-white p-[0.34in] text-slate-950 shadow-xl {{ $child['orientation'] === 'portrait' ? 'w-[8.5in] min-h-[11in]' : 'w-[11in] min-h-[8.5in]' }}" id="printable-chart">
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

                <h2 class="m-0 mb-5 text-center text-3xl font-extrabold leading-tight">{{ $child['childName'] }}'s Responsibility Chart</h2>

                <div class="grid gap-[5px] grid-cols-[repeat(21,minmax(0,1fr))]">
                    @foreach ($child['days'] as $day)
                        <div class="day-header flex items-center justify-center rounded-xl text-lg font-extrabold shadow-sm h-[0.36in]" style="grid-column: span 3; background-color: {{ $day['color'] }}">
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

                        <div class="section-row mt-2 flex h-[0.3in] items-center rounded-xl bg-slate-100 px-3 text-xs font-extrabold uppercase tracking-wider text-slate-500" style="grid-column: 1 / -1">{{ $section['name'] }}</div>

                        @foreach ($iconGroups as $iconGroup)
                            @foreach ($child['days'] as $day)
                                @php
                                    $visibleRows = array_values(array_filter(
                                        $iconGroup,
                                        fn ($r) => $r['days'][$day['key']] ?? false,
                                    ));
                                @endphp
                                @for ($slot = 0; $slot < 3; $slot++)
                                    @php($row = $visibleRows[$slot] ?? null)
                                    <div class="chart-cell icon-cell" style="background-color: {{ $day['color'] }}">
                                        @if ($row)
                                            @if ($row['paid'])
                                                <span class="paid-dot">$</span>
                                            @endif
                                            <span class="icon-render inline-flex">{!! $this->iconSvg($row['icon'] ?? 'room') !!}</span>
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

                    <div class="section-row mt-4 flex h-[0.3in] items-center rounded-xl bg-slate-100 px-3 text-xs font-extrabold uppercase tracking-wider text-slate-500" style="grid-column: 1 / -1">{{ $child['weeklyChores']['title'] }}</div>
                    <div class="grid gap-[5px] col-span-full {{ $child['orientation'] === 'portrait' ? 'grid-cols-2' : 'grid-cols-3' }}">
                        @foreach ($child['weeklyChores']['rows'] as $row)
                            <div class="chart-cell weekly-cell">
                                @if ($row['paid'])
                                    <span class="paid-dot">$</span>
                                @endif
                                @if ($row['type'] !== 'empty')
                                    <span>{{ $row['label'] }}</span>
                                @endif
                            </div>
                        @endforeach
                    </div>

                    @if ($iconLegendRows !== [])
                        <div class="section-row mt-3 flex h-[0.3in] items-center rounded-xl bg-slate-100 px-3 text-xs font-extrabold uppercase tracking-wider text-slate-500" style="grid-column: 1 / -1">Icon Chores</div>
                        <div class="grid gap-[5px] col-span-full {{ $child['orientation'] === 'portrait' ? 'grid-cols-2' : 'grid-cols-3' }}">
                            @foreach ($iconLegendRows as $row)
                                <div class="flex items-center gap-1.5 rounded-lg border border-slate-300/80 bg-white px-[0.14in] py-[0.08in] text-[0.8rem] font-bold min-h-[0.34in]">
                                    <span class="icon-legend-symbol inline-flex flex-none items-center justify-center">{!! $this->iconSvg($row['icon'] ?? 'room') !!}</span>
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
