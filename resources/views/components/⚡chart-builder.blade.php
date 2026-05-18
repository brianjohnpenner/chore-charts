<?php

use App\Models\ChoreChart;
use App\Models\MagicLoginToken;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Livewire\Component;

new class extends Component
{
    public array $chart = [];

    public string $viewMode = 'edit';

    public string $email = '';

    public string $jsonBuffer = '';

    public ?string $notice = null;

    public ?string $error = null;

    private array $dayKeys = ['sun', 'mon', 'tue', 'wed', 'thu', 'fri', 'sat'];

    public function mount(): void
    {
        $saved = Auth::user()?->choreChart;

        $this->chart = $saved ? $this->normalizeChart($saved->data) : $this->defaultChart();
        $this->email = Auth::user()?->email ?? '';
    }

    public function addChild(): void
    {
        $name = 'Child '.(count($this->chart['children']) + 1);
        $this->chart['children'][] = $this->defaultChild($name, $this->childIds());
        $this->chart['activeChildId'] = $this->chart['children'][array_key_last($this->chart['children'])]['id'];
    }

    public function duplicateChild(): void
    {
        $copy = $this->activeChild();
        $copy['childName'] .= ' Copy';
        $copy['id'] = $this->uniqueSlug($copy['childName'], $this->childIds());
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
        $this->chart['children'][$childIndex]['sections'][$sectionIndex]['rows'][] = $this->row($type);
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
        $this->chart['children'][$childIndex]['weeklyChores']['rows'][] = $this->weeklyRow($type);
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

        if (! Auth::check()) {
            $this->sendMagicLink();

            return;
        }

        ChoreChart::updateOrCreate(
            ['user_id' => Auth::id()],
            [
                'title' => $this->activeChild()['childName'].' Chart',
                'data' => $this->normalizeChart($this->chart),
            ],
        );

        $this->notice = 'Saved.';
    }

    public function sendMagicLink(): void
    {
        $this->clearMessages();
        $validated = validator(['email' => $this->email], [
            'email' => ['required', 'email'],
        ])->validate();

        $plainToken = Str::random(48);

        MagicLoginToken::create([
            'email' => strtolower($validated['email']),
            'token_hash' => hash('sha256', $plainToken),
            'chart_data' => $this->normalizeChart($this->chart),
            'expires_at' => now()->addMinutes(30),
        ]);

        $url = URL::temporarySignedRoute(
            'magic.consume',
            now()->addMinutes(30),
            ['token' => $plainToken],
        );

        Mail::raw("Open this link to sign in and save your chore chart:\n\n{$url}\n\nThis link expires in 30 minutes.", function (Message $message) use ($validated): void {
            $message->to($validated['email'])->subject('Your chore chart sign-in link');
        });

        $this->notice = 'Magic link sent. In local development, check storage/logs/laravel.log.';
    }

    public function exportJson(): void
    {
        $this->jsonBuffer = json_encode($this->normalizeChart($this->chart), JSON_PRETTY_PRINT);
    }

    public function importJson(): void
    {
        $this->clearMessages();
        $decoded = json_decode($this->jsonBuffer, true);

        if (! is_array($decoded)) {
            $this->error = 'That JSON could not be read.';

            return;
        }

        $this->chart = $this->normalizeChart($decoded);
        $this->notice = 'Imported.';
    }

    public function resetChart(): void
    {
        $this->chart = $this->defaultChart();
        $this->notice = 'Reset to defaults.';
    }

    public function activeChild(): array
    {
        return $this->chart['children'][$this->activeChildIndex()] ?? $this->chart['children'][0];
    }

    public function iconSvg(string $icon): string
    {
        return match ($icon) {
            'bed' => '<svg viewBox="0 0 24 24"><path d="M4 11V6a2 2 0 0 1 2-2h5a3 3 0 0 1 3 3v4"/><path d="M3 19v-8h18a2 2 0 0 1 2 2v6"/><path d="M3 16h20"/><path d="M7 19v-3"/><path d="M19 19v-3"/></svg>',
            'toothbrush' => '<svg viewBox="0 0 24 24"><path d="m5 19 8-8"/><path d="m10 8 6-6 4 4-6 6"/><path d="m13 5 6 6"/><path d="M4 20l2-5 3 3Z"/></svg>',
            'laundry' => '<svg viewBox="0 0 24 24"><rect x="5" y="3" width="14" height="18" rx="2"/><path d="M8 7h.01"/><path d="M12 7h4"/><circle cx="12" cy="14" r="4"/><path d="M9 14c2 1.3 4 1.3 6 0"/></svg>',
            'dishes' => '<svg viewBox="0 0 24 24"><path d="M4 10a8 8 0 0 0 16 0Z"/><path d="M6 18h12"/><path d="M9 21h6"/><path d="M8 4v3"/><path d="M12 3v4"/><path d="M16 4v3"/></svg>',
            'trash' => '<svg viewBox="0 0 24 24"><path d="M4 7h16"/><path d="M9 7V4h6v3"/><path d="m7 7 1 14h8l1-14"/><path d="M10 11v6"/><path d="M14 11v6"/></svg>',
            'backpack' => '<svg viewBox="0 0 24 24"><path d="M8 7V6a4 4 0 0 1 8 0v1"/><rect x="5" y="7" width="14" height="14" rx="3"/><path d="M8 13h8"/><path d="M9 17h6"/><path d="M5 12H3v5h2"/><path d="M19 12h2v5h-2"/></svg>',
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

    private function defaultChart(): array
    {
        $child = $this->defaultChild('Jack', []);

        return [
            'version' => 3,
            'activeChildId' => $child['id'],
            'children' => [$child],
        ];
    }

    private function defaultChild(string $name, array $existingIds): array
    {
        return [
            'id' => $this->uniqueSlug($name, $existingIds),
            'childName' => $name,
            'orientation' => 'landscape',
            'days' => $this->defaultDays(),
            'sections' => [
                [
                    'id' => 'morning',
                    'name' => 'Morning',
                    'rows' => [
                        $this->row('icon', 'Make bed', 'bed'),
                        $this->row('icon', 'Brush teeth', 'toothbrush'),
                        $this->row('icon', 'Laundry', 'laundry'),
                        $this->row('regular', 'Feed cat', 'room'),
                    ],
                ],
                [
                    'id' => 'daytime',
                    'name' => 'Daytime',
                    'rows' => [
                        $this->row('regular', 'Put dishes away', 'dishes'),
                        $this->row('empty'),
                    ],
                ],
                [
                    'id' => 'before-bed',
                    'name' => 'Before Bed',
                    'rows' => [
                        $this->row('regular', 'Pick up room', 'room'),
                        $this->row('empty'),
                    ],
                ],
            ],
            'weeklyChores' => [
                'title' => 'Weekly Chores',
                'rows' => [
                    $this->weeklyRow('regular', 'Clean bedroom', true),
                    $this->weeklyRow('regular', 'Put away laundry'),
                    $this->weeklyRow('empty'),
                    $this->weeklyRow('empty'),
                ],
            ],
        ];
    }

    private function defaultDays(): array
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

    private function row(string $type, string $label = '', string $icon = 'room', bool $paid = false): array
    {
        return [
            'id' => $type.'-'.Str::random(8),
            'type' => in_array($type, ['icon', 'regular', 'empty'], true) ? $type : 'regular',
            'label' => $label,
            'icon' => $icon,
            'paid' => $paid,
            'days' => array_fill_keys($this->dayKeys, true),
        ];
    }

    private function weeklyRow(string $type, string $label = '', bool $paid = false): array
    {
        return [
            'id' => 'weekly-'.$type.'-'.Str::random(8),
            'type' => $type === 'empty' ? 'empty' : 'regular',
            'label' => $label,
            'paid' => $paid,
        ];
    }

    private function normalizeChart(array $chart): array
    {
        if (! isset($chart['children']) || ! is_array($chart['children']) || count($chart['children']) === 0) {
            return $this->defaultChart();
        }

        $children = [];
        $ids = [];

        foreach ($chart['children'] as $child) {
            $name = trim((string) ($child['childName'] ?? 'Child')) ?: 'Child';
            $id = $this->uniqueSlug($child['id'] ?? $name, $ids);
            $ids[] = $id;

            $children[] = [
                'id' => $id,
                'childName' => $name,
                'orientation' => ($child['orientation'] ?? 'landscape') === 'portrait' ? 'portrait' : 'landscape',
                'days' => $this->normalizeDays($child['days'] ?? []),
                'sections' => $this->normalizeSections($child['sections'] ?? []),
                'weeklyChores' => $this->normalizeWeekly($child['weeklyChores'] ?? []),
            ];
        }

        $activeId = $chart['activeChildId'] ?? $children[0]['id'];

        return [
            'version' => 3,
            'activeChildId' => in_array($activeId, array_column($children, 'id'), true) ? $activeId : $children[0]['id'],
            'children' => $children,
        ];
    }

    private function normalizeDays(array $days): array
    {
        $defaults = $this->defaultDays();

        return array_map(function (array $default) use ($days): array {
            $incoming = collect($days)->firstWhere('key', $default['key']) ?? [];

            return [
                'key' => $default['key'],
                'label' => trim((string) ($incoming['label'] ?? $default['label'])) ?: $default['label'],
                'color' => preg_match('/^#[0-9a-fA-F]{6}$/', (string) ($incoming['color'] ?? '')) ? $incoming['color'] : $default['color'],
            ];
        }, $defaults);
    }

    private function normalizeSections(array $sections): array
    {
        if ($sections === []) {
            return $this->defaultChild('Child', [])['sections'];
        }

        return array_values(array_map(fn (array $section): array => [
            'id' => (string) ($section['id'] ?? 'section-'.Str::random(8)),
            'name' => trim((string) ($section['name'] ?? 'Section')) ?: 'Section',
            'rows' => $this->normalizeRows($section['rows'] ?? []),
        ], $sections));
    }

    private function normalizeRows(array $rows): array
    {
        return array_values(array_map(function (array $row): array {
            $normalized = $this->row($row['type'] ?? 'regular');
            $normalized['id'] = (string) ($row['id'] ?? $normalized['id']);
            $normalized['label'] = (string) ($row['label'] ?? '');
            $normalized['icon'] = in_array(($row['icon'] ?? 'room'), ['bed', 'toothbrush', 'laundry', 'dishes', 'trash', 'backpack', 'room'], true) ? $row['icon'] : 'room';
            $normalized['paid'] = (bool) ($row['paid'] ?? false);
            $normalized['days'] = array_merge(array_fill_keys($this->dayKeys, true), array_intersect_key((array) ($row['days'] ?? []), array_fill_keys($this->dayKeys, true)));

            return $normalized;
        }, $rows));
    }

    private function normalizeWeekly(array $weekly): array
    {
        $rows = $weekly['rows'] ?? [];

        return [
            'title' => trim((string) ($weekly['title'] ?? 'Weekly Chores')) ?: 'Weekly Chores',
            'rows' => array_values(array_map(function (array $row): array {
                $normalized = $this->weeklyRow($row['type'] ?? 'regular');
                $normalized['id'] = (string) ($row['id'] ?? $normalized['id']);
                $normalized['label'] = (string) ($row['label'] ?? '');
                $normalized['paid'] = (bool) ($row['paid'] ?? false);

                return $normalized;
            }, is_array($rows) ? $rows : [])),
        ];
    }

    private function uniqueSlug(string $value, array $existingIds): string
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

    private function clearMessages(): void
    {
        $this->notice = null;
        $this->error = null;
    }
};
?>

<div class="app-shell">
    <header class="topbar no-print">
        <div>
            <h1>Chore Charts</h1>
            <p>Build the chart, print it, and email yourself a magic link when you want it saved.</p>
        </div>

        <div class="topbar-actions">
            <div class="mode-toggle" role="group" aria-label="View mode">
                <button type="button" class="{{ $viewMode === 'edit' ? 'active' : '' }}" wire:click="$set('viewMode', 'edit')">Editor</button>
                <button type="button" class="{{ $viewMode === 'preview' ? 'active' : '' }}" wire:click="$set('viewMode', 'preview')">Print View</button>
            </div>
            <button type="button" class="primary" onclick="window.print()">Print</button>
            <a class="topbar-link" href="{{ route('privacy') }}">Privacy</a>
            @auth
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit">Sign out</button>
                </form>
            @endauth
        </div>
    </header>

    @if (session('status') || $notice || $error)
        <div class="status-line no-print {{ $error ? 'error' : '' }}">
            {{ $error ?: session('status') ?: $notice }}
        </div>
    @endif

    <section class="save-strip no-print">
        @auth
            <div>
                <strong>Signed in as {{ auth()->user()->email }}</strong>
                <span>Your chart is private to this email address.</span>
            </div>
            <button type="button" class="primary" wire:click="saveChart">Save Chart</button>
        @else
            <div>
                <strong>Want to save this chart?</strong>
                <span>Enter your email and open the magic link. No password needed.</span>
            </div>
            <div class="magic-form">
                <input type="email" wire:model.live="email" placeholder="you@example.com">
                <button type="button" class="primary" wire:click="sendMagicLink">Send Link</button>
            </div>
            @error('email') <span class="form-error">{{ $message }}</span> @enderror
        @endauth
    </section>

    @php($child = $this->activeChild())

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
                                    <select wire:model.live="chart.children.{{ $this->activeChildIndex() }}.sections.{{ $sectionIndex }}.rows.{{ $rowIndex }}.icon">
                                        <option value="bed">Bed</option>
                                        <option value="toothbrush">Toothbrush</option>
                                        <option value="laundry">Laundry</option>
                                        <option value="dishes">Dishes</option>
                                        <option value="trash">Trash</option>
                                        <option value="backpack">Backpack</option>
                                        <option value="room">Room</option>
                                    </select>
                                @endif

                                @if ($row['type'] === 'empty')
                                    <span class="empty-label">Empty write-in line</span>
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
                            <span class="empty-label">Empty write-in line</span>
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
                <h2>{{ $child['childName'] }}'s Responsibility Chart</h2>

                <div class="chart-grid" style="--day-count: {{ count($child['days']) }}">
                    @foreach ($child['days'] as $day)
                        <div class="day-column" style="--day-color: {{ $day['color'] }}">
                            <h3>{{ $day['label'] }}</h3>

                            @foreach ($child['sections'] as $section)
                                <div class="preview-section">
                                    <h4>{{ $section['name'] }}</h4>

                                    <div class="preview-icons">
                                        @foreach ($section['rows'] as $row)
                                            @if ($row['type'] === 'icon' && ($row['days'][$day['key']] ?? false))
                                                <div class="preview-icon">
                                                    {!! $this->iconSvg($row['icon'] ?? 'room') !!}
                                                    @if ($row['paid']) <span class="paid-dot">$</span> @endif
                                                    @if ($row['label']) <small>{{ $row['label'] }}</small> @endif
                                                </div>
                                            @endif
                                        @endforeach
                                    </div>

                                    @foreach ($section['rows'] as $row)
                                        @continue($row['type'] === 'icon' || ! ($row['days'][$day['key']] ?? false))
                                        <div class="preview-row {{ $row['type'] === 'empty' ? 'write-in' : '' }}">
                                            @if ($row['type'] === 'empty')
                                                <span></span>
                                            @else
                                                <span>{{ $row['label'] }}</span>
                                            @endif
                                            @if ($row['paid']) <span class="paid-dot">$</span> @endif
                                        </div>
                                    @endforeach
                                </div>
                            @endforeach
                        </div>
                    @endforeach
                </div>

                <section class="weekly-preview">
                    <h3>{{ $child['weeklyChores']['title'] }}</h3>
                    <div class="weekly-preview-grid">
                        @foreach ($child['weeklyChores']['rows'] as $row)
                            <div class="preview-row {{ $row['type'] === 'empty' ? 'write-in' : '' }}">
                                @if ($row['type'] === 'empty')
                                    <span></span>
                                @else
                                    <span>{{ $row['label'] }}</span>
                                @endif
                                @if ($row['paid']) <span class="paid-dot">$</span> @endif
                            </div>
                        @endforeach
                    </div>
                </section>
            </article>
        </section>
    </main>
</div>
