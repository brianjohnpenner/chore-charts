<?php

use App\Mail\MagicLink;
use App\Models\Chart;
use App\Models\User;
use App\Support\ChartDefaults;
use App\Support\ChartIcons;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Livewire\Component;

new class extends Component {
    public int $chartId;
    public array $chart = [];
    public string $email = '';
    public string $viewMode = 'edit';
    public bool $hasEdited = false;

    public function mount(int $chartId): void
    {
        $this->chartId = $chartId;
        $this->chart = Chart::findOrFail($chartId)->data;
    }

    public function updated(string $name): void
    {
        if (str_starts_with($name, 'chart')) {
            $this->markEdited();
        }
    }

    protected function save(): void
    {
        Chart::where('id', $this->chartId)->update(['data' => $this->chart]);
    }

    protected function markEdited(): void
    {
        $this->hasEdited = true;
        $this->save();
    }

    public function activeChildIndex(): int
    {
        foreach ($this->chart['children'] as $i => $child) {
            if ($child['id'] === $this->chart['activeChildId']) {
                return $i;
            }
        }
        return 0;
    }

    public function getActiveChildProperty(): array
    {
        return $this->chart['children'][$this->activeChildIndex()];
    }

    public function getIconsProperty(): array
    {
        return ChartIcons::svgs();
    }

    public function getIconOptionsProperty(): array
    {
        return ChartIcons::options();
    }

    public function setActiveChild(string $id): void
    {
        $this->chart['activeChildId'] = $id;
    }

    public function addChild(): void
    {
        $existingIds = array_column($this->chart['children'], 'id');
        $child = ChartDefaults::defaultChild('New Child', $existingIds);
        $this->chart['children'][] = $child;
        $this->chart['activeChildId'] = $child['id'];
        $this->markEdited();
    }

    public function duplicateChild(): void
    {
        $i = $this->activeChildIndex();
        $copy = $this->chart['children'][$i];
        $base = $copy['childName'].' Copy';
        $existing = array_column($this->chart['children'], 'id');
        $id = ChartDefaults::slugify($base);
        $n = 2;
        while (in_array($id, $existing, true)) {
            $id = ChartDefaults::slugify($base).'-'.$n++;
        }
        $copy['id'] = $id;
        $copy['childName'] = $base;
        $this->chart['children'][] = $copy;
        $this->chart['activeChildId'] = $id;
        $this->markEdited();
    }

    public function deleteChild(): void
    {
        if (count($this->chart['children']) === 1) {
            return;
        }
        $i = $this->activeChildIndex();
        array_splice($this->chart['children'], $i, 1);
        $newIdx = max(0, $i - 1);
        $this->chart['activeChildId'] = $this->chart['children'][$newIdx]['id'];
        $this->markEdited();
    }

    public function setOrientation(string $orientation): void
    {
        $i = $this->activeChildIndex();
        $this->chart['children'][$i]['orientation'] = $orientation === 'portrait' ? 'portrait' : 'landscape';
        $this->markEdited();
    }

    public function addSection(): void
    {
        $i = $this->activeChildIndex();
        $this->chart['children'][$i]['sections'][] = [
            'id' => ChartDefaults::uid('section'),
            'name' => 'New Section',
            'rows' => [],
        ];
        $this->markEdited();
    }

    public function deleteSection(int $sectionIndex): void
    {
        $i = $this->activeChildIndex();
        array_splice($this->chart['children'][$i]['sections'], $sectionIndex, 1);
        $this->markEdited();
    }

    public function moveSection(int $sectionIndex, int $direction): void
    {
        $i = $this->activeChildIndex();
        $list = &$this->chart['children'][$i]['sections'];
        $next = $sectionIndex + $direction;
        if ($next < 0 || $next >= count($list)) {
            return;
        }
        $item = array_splice($list, $sectionIndex, 1)[0];
        array_splice($list, $next, 0, [$item]);
        $this->markEdited();
    }

    public function addRow(int $sectionIndex, string $type): void
    {
        $i = $this->activeChildIndex();
        $label = $type === 'regular' ? 'New chore' : '';
        $this->chart['children'][$i]['sections'][$sectionIndex]['rows'][] =
            ChartDefaults::choreRow($type, $label, 'room');
        $this->markEdited();
    }

    public function deleteRow(int $sectionIndex, int $rowIndex): void
    {
        $i = $this->activeChildIndex();
        array_splice($this->chart['children'][$i]['sections'][$sectionIndex]['rows'], $rowIndex, 1);
        $this->markEdited();
    }

    public function moveRow(int $sectionIndex, int $rowIndex, int $direction): void
    {
        $i = $this->activeChildIndex();
        $list = &$this->chart['children'][$i]['sections'][$sectionIndex]['rows'];
        $next = $rowIndex + $direction;
        if ($next < 0 || $next >= count($list)) {
            return;
        }
        $item = array_splice($list, $rowIndex, 1)[0];
        array_splice($list, $next, 0, [$item]);
        $this->markEdited();
    }

    public function changeRowType(int $sectionIndex, int $rowIndex, string $type): void
    {
        if (! in_array($type, ChartDefaults::ROW_TYPES, true)) {
            return;
        }
        $i = $this->activeChildIndex();
        $row = &$this->chart['children'][$i]['sections'][$sectionIndex]['rows'][$rowIndex];
        $row['type'] = $type;
        if ($type === 'empty') {
            $row['label'] = '';
        }
        if (! isset($row['icon']) || ! in_array($row['icon'], ChartDefaults::ICONS, true)) {
            $row['icon'] = 'room';
        }
        $this->markEdited();
    }

    public function addWeeklyRow(string $type): void
    {
        $i = $this->activeChildIndex();
        $label = $type === 'regular' ? 'New weekly chore' : '';
        $this->chart['children'][$i]['weeklyChores']['rows'][] =
            ChartDefaults::weeklyRow($type, $label);
        $this->markEdited();
    }

    public function deleteWeeklyRow(int $rowIndex): void
    {
        $i = $this->activeChildIndex();
        array_splice($this->chart['children'][$i]['weeklyChores']['rows'], $rowIndex, 1);
        $this->markEdited();
    }

    public function moveWeeklyRow(int $rowIndex, int $direction): void
    {
        $i = $this->activeChildIndex();
        $list = &$this->chart['children'][$i]['weeklyChores']['rows'];
        $next = $rowIndex + $direction;
        if ($next < 0 || $next >= count($list)) {
            return;
        }
        $item = array_splice($list, $rowIndex, 1)[0];
        array_splice($list, $next, 0, [$item]);
        $this->markEdited();
    }

    public function changeWeeklyRowType(int $rowIndex, string $type): void
    {
        $i = $this->activeChildIndex();
        $row = &$this->chart['children'][$i]['weeklyChores']['rows'][$rowIndex];
        $row['type'] = $type === 'empty' ? 'empty' : 'regular';
        if ($row['type'] === 'empty') {
            $row['label'] = '';
        }
        $this->markEdited();
    }

    public function setPreview(string $mode): void
    {
        $this->viewMode = $mode === 'preview' ? 'preview' : 'edit';
    }

    public function sendMagicLink(): void
    {
        $this->validate(['email' => 'required|email']);

        $user = User::firstOrCreate(['email' => $this->email]);

        $url = URL::temporarySignedRoute(
            'magic.login',
            now()->addHour(),
            ['user' => $user->id]
        );

        Mail::to($user->email)->send(new MagicLink($url));

        session()->flash('status', "Sent a sign-in link to {$user->email}. In dev, look in storage/logs/laravel.log.");
        $this->email = '';
    }
};
?>

<div class="cc-app" x-data="{ printMode: 'selected' }" :class="{ 'cc-printing-all': printMode === 'all', 'cc-printing-selected': printMode === 'selected' }">
    @php
        $activeChild = $this->activeChild;
        $activeIdx = $this->activeChildIndex();
        $icons = $this->icons;
        $iconOptions = $this->iconOptions;
        $days = $activeChild['days'];
    @endphp

    <header class="cc-editor-panel">
        <div class="cc-editor-header">
            <div>
                <h1 class="cc-title">Chore Charts</h1>
                <p class="cc-subtitle">Build the chart by row, then preview or print.</p>
            </div>
            <div class="cc-print-actions">
                <button type="button" wire:click="setPreview('edit')" @class(['cc-tab', 'cc-tab-active' => $viewMode === 'edit'])>Edit</button>
                <button type="button" wire:click="setPreview('preview')" @class(['cc-tab', 'cc-tab-active' => $viewMode === 'preview'])>Preview</button>
                <button type="button" class="cc-primary" @click="printMode='selected'; setTimeout(()=>window.print())">Print Current</button>
                <button type="button" @click="printMode='all'; setTimeout(()=>window.print())">Print All</button>
            </div>
        </div>

        @auth
            <div class="cc-account-bar">
                <span>Signed in as <strong>{{ auth()->user()->email }}</strong> — changes save automatically.</span>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="cc-link">Sign out</button>
                </form>
                @if (session('status'))
                    <p class="cc-flash">{{ session('status') }}</p>
                @endif
            </div>
        @else
            <div @class(['cc-save-card', 'cc-save-card-edited' => $hasEdited])>
                <div class="cc-save-card-copy">
                    <strong>{{ $hasEdited ? 'Save your chart before you lose it' : 'Save your chart long-term' }}</strong>
                    <span>This chart lives in your browser only. Add your email and we'll send a one-click sign-in link so you can come back to it anywhere.</span>
                </div>
                <form wire:submit.prevent="sendMagicLink" class="cc-magic-form">
                    <input type="email" wire:model="email" placeholder="you@example.com" aria-label="Email address" required>
                    <button type="submit" class="cc-primary">Email me a link</button>
                </form>
                @error('email') <p class="cc-error">{{ $message }}</p> @enderror
                @if (session('status'))
                    <p class="cc-flash">{{ session('status') }}</p>
                @endif
            </div>
        @endauth

        <div class="cc-settings">
            <label class="cc-field">
                <span>Child</span>
                <select wire:change="setActiveChild($event.target.value)">
                    @foreach ($chart['children'] as $child)
                        <option value="{{ $child['id'] }}" @selected($child['id'] === $chart['activeChildId'])>{{ $child['childName'] }}</option>
                    @endforeach
                </select>
            </label>
            <label class="cc-field">
                <span>Name</span>
                <input type="text" wire:model.blur="chart.children.{{ $activeIdx }}.childName">
            </label>
            <div class="cc-field">
                <span>Orientation</span>
                <div class="cc-segmented">
                    <button type="button" wire:click="setOrientation('landscape')" @class(['cc-tab-active' => $activeChild['orientation'] === 'landscape'])>Landscape</button>
                    <button type="button" wire:click="setOrientation('portrait')" @class(['cc-tab-active' => $activeChild['orientation'] === 'portrait'])>Portrait</button>
                </div>
            </div>
            <div class="cc-btn-row">
                <button type="button" wire:click="addChild">Add Child</button>
                <button type="button" wire:click="duplicateChild">Duplicate</button>
                <button type="button" wire:click="deleteChild" @disabled(count($chart['children']) === 1)>Delete</button>
            </div>
        </div>
    </header>

    @if ($viewMode === 'edit')
    <section class="cc-workspace">
        @foreach ($activeChild['sections'] as $sectionIndex => $section)
            <section class="cc-section" wire:key="sec-{{ $section['id'] }}">
                <div class="cc-section-toolbar">
                    <input type="text" wire:model.blur="chart.children.{{ $activeIdx }}.sections.{{ $sectionIndex }}.name">
                    <div class="cc-btn-row">
                        <button type="button" wire:click="addRow({{ $sectionIndex }}, 'icon')">Add Icon</button>
                        <button type="button" wire:click="addRow({{ $sectionIndex }}, 'regular')">Add Chore</button>
                        <button type="button" wire:click="addRow({{ $sectionIndex }}, 'empty')">Add Empty</button>
                        <button type="button" wire:click="moveSection({{ $sectionIndex }}, -1)" aria-label="Move section up">↑</button>
                        <button type="button" wire:click="moveSection({{ $sectionIndex }}, 1)" aria-label="Move section down">↓</button>
                        <button type="button" class="cc-danger" wire:click="deleteSection({{ $sectionIndex }})" aria-label="Delete section">✕</button>
                    </div>
                </div>

                <div class="cc-rows">
                    @foreach ($section['rows'] as $rowIndex => $row)
                        <div class="cc-row" wire:key="row-{{ $row['id'] }}">
                            <div class="cc-row-main">
                                <select wire:change="changeRowType({{ $sectionIndex }}, {{ $rowIndex }}, $event.target.value)">
                                    <option value="icon" @selected($row['type'] === 'icon')>Icon</option>
                                    <option value="regular" @selected($row['type'] === 'regular')>Regular</option>
                                    <option value="empty" @selected($row['type'] === 'empty')>Empty</option>
                                </select>

                                @if ($row['type'] === 'icon')
                                    <select wire:model.blur="chart.children.{{ $activeIdx }}.sections.{{ $sectionIndex }}.rows.{{ $rowIndex }}.icon">
                                        @foreach ($iconOptions as $key => $label)
                                            <option value="{{ $key }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                @endif

                                @if ($row['type'] !== 'empty')
                                    <input type="text"
                                        wire:model.blur="chart.children.{{ $activeIdx }}.sections.{{ $sectionIndex }}.rows.{{ $rowIndex }}.label"
                                        placeholder="{{ $row['type'] === 'icon' ? 'Optional label' : 'Chore name' }}">
                                @else
                                    <span class="cc-muted">Empty write-in line</span>
                                @endif
                            </div>

                            <div class="cc-row-days">
                                @foreach ($days as $day)
                                    <label class="cc-day-toggle" style="--day-color: {{ $day['color'] }}">
                                        <input type="checkbox"
                                            wire:model.live="chart.children.{{ $activeIdx }}.sections.{{ $sectionIndex }}.rows.{{ $rowIndex }}.days.{{ $day['key'] }}">
                                        <span>{{ $day['label'] }}</span>
                                    </label>
                                @endforeach
                            </div>

                            <label class="cc-paid">
                                <input type="checkbox"
                                    wire:model.live="chart.children.{{ $activeIdx }}.sections.{{ $sectionIndex }}.rows.{{ $rowIndex }}.paid">
                                {!! $icons['coin'] !!}
                            </label>

                            <div class="cc-btn-row">
                                <button type="button" wire:click="moveRow({{ $sectionIndex }}, {{ $rowIndex }}, -1)" aria-label="Move row up">↑</button>
                                <button type="button" wire:click="moveRow({{ $sectionIndex }}, {{ $rowIndex }}, 1)" aria-label="Move row down">↓</button>
                                <button type="button" class="cc-danger" wire:click="deleteRow({{ $sectionIndex }}, {{ $rowIndex }})" aria-label="Delete row">✕</button>
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>
        @endforeach

        <div class="cc-btn-row">
            <button type="button" wire:click="addSection">Add Section</button>
        </div>

        <section class="cc-section">
            <div class="cc-section-toolbar">
                <input type="text" wire:model.blur="chart.children.{{ $activeIdx }}.weeklyChores.title">
                <div class="cc-btn-row">
                    <button type="button" wire:click="addWeeklyRow('regular')">Add Weekly Chore</button>
                    <button type="button" wire:click="addWeeklyRow('empty')">Add Empty</button>
                </div>
            </div>
            <div class="cc-rows">
                @foreach ($activeChild['weeklyChores']['rows'] as $rowIndex => $row)
                    <div class="cc-row" wire:key="wk-{{ $row['id'] }}">
                        <div class="cc-row-main">
                            <select wire:change="changeWeeklyRowType({{ $rowIndex }}, $event.target.value)">
                                <option value="regular" @selected($row['type'] === 'regular')>Regular</option>
                                <option value="empty" @selected($row['type'] === 'empty')>Empty</option>
                            </select>
                            @if ($row['type'] === 'regular')
                                <input type="text"
                                    wire:model.blur="chart.children.{{ $activeIdx }}.weeklyChores.rows.{{ $rowIndex }}.label"
                                    placeholder="Weekly chore">
                            @else
                                <span class="cc-muted">Empty write-in line</span>
                            @endif
                        </div>
                        <label class="cc-paid">
                            <input type="checkbox"
                                wire:model.live="chart.children.{{ $activeIdx }}.weeklyChores.rows.{{ $rowIndex }}.paid">
                            {!! $icons['coin'] !!}
                        </label>
                        <div class="cc-btn-row">
                            <button type="button" wire:click="moveWeeklyRow({{ $rowIndex }}, -1)" aria-label="Move up">↑</button>
                            <button type="button" wire:click="moveWeeklyRow({{ $rowIndex }}, 1)" aria-label="Move down">↓</button>
                            <button type="button" class="cc-danger" wire:click="deleteWeeklyRow({{ $rowIndex }})" aria-label="Delete">✕</button>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
    </section>
    @endif

    @if ($viewMode === 'preview')
        <section class="cc-preview">
            @include('partials.chart-page', ['child' => $activeChild, 'icons' => $icons])
        </section>
    @endif

    <div class="cc-print-all" aria-hidden="true">
        @foreach ($chart['children'] as $child)
            @include('partials.chart-page', ['child' => $child, 'icons' => $icons])
        @endforeach
    </div>

    <footer class="cc-footer">
        <a href="{{ route('privacy') }}">Privacy policy</a>
    </footer>
</div>
