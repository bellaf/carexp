<x-layouts::app :title="__('History')">
    <section
        x-data="{
            selectedEntry: null,
            entries: {{ Illuminate\Support\Js::from($timeline->keyBy('id')) }},
            openEntry(entryId) {
                this.selectedEntry = this.entries[entryId] ?? null;
            }
        }"
        class="w-full space-y-6"
    >
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <flux:heading size="xl">{{ __('History') }}</flux:heading>
                <flux:subheading>{{ __('Review the full event timeline for a vehicle.') }}</flux:subheading>
            </div>

            @if ($selectedCar !== null)
                <div class="rounded-xl border border-zinc-200 bg-zinc-50/70 px-4 py-3 text-sm dark:border-zinc-700 dark:bg-zinc-900/40">
                    <div class="text-zinc-500 dark:text-zinc-400">{{ __('Current focus') }}</div>
                    <div class="font-medium text-zinc-900 dark:text-zinc-100">
                        {{ trim(collect([$selectedCar->year, $selectedCar->make, $selectedCar->model])->filter()->implode(' ')) }}
                    </div>
                </div>
            @endif
        </div>

        <flux:card class="space-y-4">
            <form method="GET" action="{{ route('history.index') }}" class="space-y-3 md:space-y-0 md:flex md:flex-wrap md:items-end md:gap-3">
                <div class="w-full sm:w-72">
                    <flux:label for="car_id">{{ __('Car') }}</flux:label>
                    <select id="car_id" name="car_id" onchange="this.form.submit()" class="mt-2 w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-900">
                        @foreach ($cars as $car)
                            <option value="{{ $car->id }}" @selected($selectedCarId === $car->id)>
                                {{ trim(collect([$car->year, $car->make, $car->model])->filter()->implode(' ')) }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="w-full sm:w-56">
                    <flux:label for="type">{{ __('Event Type') }}</flux:label>
                    <select id="type" name="type" onchange="this.form.submit()" class="mt-2 w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-900">
                        @foreach ($eventTypeOptions as $value => $label)
                            <option value="{{ $value }}" @selected($selectedEventType === $value)>{{ __($label) }}</option>
                        @endforeach
                    </select>
                </div>
            </form>

            <div class="grid gap-3 sm:grid-cols-3">
                <div class="rounded-xl border border-zinc-200 bg-zinc-50/70 p-4 dark:border-zinc-700 dark:bg-zinc-900/40">
                    <flux:text>{{ __('Events') }}</flux:text>
                    <flux:heading size="lg">{{ $timeline->count() }}</flux:heading>
                </div>
                <div class="rounded-xl border border-zinc-200 bg-zinc-50/70 p-4 dark:border-zinc-700 dark:bg-zinc-900/40">
                    <flux:text>{{ __('With Attachments') }}</flux:text>
                    <flux:heading size="lg">{{ $timeline->where('has_attachments', true)->count() }}</flux:heading>
                </div>
                <div class="rounded-xl border border-zinc-200 bg-zinc-50/70 p-4 dark:border-zinc-700 dark:bg-zinc-900/40">
                    <flux:text>{{ __('Latest Event') }}</flux:text>
                    <flux:heading size="base">{{ $timeline->first()['date'] ?? __('No history yet') }}</flux:heading>
                </div>
            </div>
        </flux:card>

        @if ($timeline->isEmpty())
            <flux:card>
                <flux:text>{{ __('No history entries were found for the selected car and event type.') }}</flux:text>
            </flux:card>
        @else
            <flux:card class="space-y-4">
                <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Tap any entry to view the full summary.') }}</flux:text>

                <div class="space-y-3 md:hidden">
                    @foreach ($timeline as $entry)
                        <button
                            type="button"
                            class="w-full rounded-xl border border-zinc-200 bg-zinc-50/70 p-4 text-left transition hover:bg-zinc-100 dark:border-zinc-700 dark:bg-zinc-900/40 dark:hover:bg-zinc-900"
                            x-on:click="openEntry(@js($entry['id']))"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <div class="text-sm text-zinc-500 dark:text-zinc-400">{{ $entry['date'] }} · {{ $entry['type'] }}</div>
                                    <div class="truncate font-medium text-zinc-900 dark:text-zinc-100">{{ $entry['title'] }}</div>
                                    <div class="truncate text-sm text-zinc-600 dark:text-zinc-300">{{ $entry['subtitle'] }}</div>
                                </div>

                                <div class="text-right">
                                    <div class="{{ $entry['amount_type'] === 'income' ? 'text-emerald-700 dark:text-emerald-400' : 'text-rose-700 dark:text-rose-400' }}">
                                        {{ $entry['amount'] }}
                                    </div>
                                    @if ($entry['has_attachments'])
                                        <div class="text-xs text-sky-700 dark:text-sky-300">{{ __('Docs attached') }}</div>
                                    @endif
                                </div>
                            </div>
                        </button>
                    @endforeach
                </div>

                <div class="hidden overflow-x-auto rounded-lg border border-zinc-200 dark:border-zinc-700 md:block">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-zinc-50 text-zinc-700 dark:bg-zinc-900 dark:text-zinc-300">
                            <tr>
                                <th class="px-3 py-2 font-medium">{{ __('Date') }}</th>
                                <th class="px-3 py-2 font-medium">{{ __('Type') }}</th>
                                <th class="px-3 py-2 font-medium">{{ __('Details') }}</th>
                                <th class="px-3 py-2 font-medium">{{ __('Car') }}</th>
                                <th class="px-3 py-2 text-right font-medium">{{ __('Amount') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($timeline as $entry)
                                <tr
                                    class="cursor-pointer border-t border-zinc-200 hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-900/40"
                                    x-on:click="openEntry(@js($entry['id']))"
                                >
                                    <td class="px-3 py-3">{{ $entry['date'] }}</td>
                                    <td class="px-3 py-3">
                                        <div class="font-medium">{{ $entry['type'] }}</div>
                                        @if ($entry['has_attachments'])
                                            <div class="text-xs text-sky-700 dark:text-sky-300">{{ __('Docs attached') }}</div>
                                        @endif
                                    </td>
                                    <td class="px-3 py-3">
                                        <div class="font-medium">{{ $entry['title'] }}</div>
                                        <div class="text-zinc-500 dark:text-zinc-400">{{ $entry['subtitle'] }}</div>
                                    </td>
                                    <td class="px-3 py-3">{{ $entry['car'] }}</td>
                                    <td class="px-3 py-3 text-right {{ $entry['amount_type'] === 'income' ? 'text-emerald-700 dark:text-emerald-400' : 'text-rose-700 dark:text-rose-400' }}">{{ $entry['amount'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </flux:card>
        @endif

        <div
            x-show="selectedEntry !== null"
            x-cloak
            x-on:keydown.escape.window="selectedEntry = null"
            class="fixed inset-0 z-50 flex items-center justify-center bg-zinc-950/55 p-4"
        >
            <div
                x-show="selectedEntry !== null"
                x-on:click.outside="selectedEntry = null"
                class="max-h-[90vh] w-full max-w-3xl overflow-y-auto rounded-2xl border border-zinc-300 bg-white p-6 shadow-2xl ring-1 ring-black/10 dark:border-zinc-600 dark:bg-zinc-800 dark:ring-white/10"
            >
                <template x-if="selectedEntry">
                    <div class="space-y-5">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <flux:heading x-text="selectedEntry.title"></flux:heading>
                                <flux:subheading x-text="`${selectedEntry.date} · ${selectedEntry.type}`"></flux:subheading>
                            </div>
                            <flux:button type="button" variant="ghost" x-on:click="selectedEntry = null">{{ __('Close') }}</flux:button>
                        </div>

                        <div class="grid gap-4 md:grid-cols-2">
                            <div class="rounded-xl border border-zinc-200 bg-zinc-50/70 p-4 dark:border-zinc-700 dark:bg-zinc-900/40">
                                <div class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Car') }}</div>
                                <div class="font-medium text-zinc-900 dark:text-zinc-100" x-text="selectedEntry.car"></div>
                            </div>
                            <div class="rounded-xl border border-zinc-200 bg-zinc-50/70 p-4 dark:border-zinc-700 dark:bg-zinc-900/40">
                                <div class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Amount') }}</div>
                                <div class="font-medium" x-bind:class="selectedEntry.amount_type === 'income' ? 'text-emerald-700 dark:text-emerald-400' : 'text-rose-700 dark:text-rose-400'" x-text="selectedEntry.amount"></div>
                            </div>
                        </div>

                        <div class="space-y-3">
                            <template x-for="[label, value] in Object.entries(selectedEntry.details)" :key="label">
                                <div class="rounded-xl border border-zinc-200 bg-zinc-50/70 p-4 dark:border-zinc-700 dark:bg-zinc-900/40">
                                    <div class="text-sm text-zinc-500 dark:text-zinc-400" x-text="label"></div>
                                    <div class="font-medium text-zinc-900 dark:text-zinc-100" x-text="value"></div>
                                </div>
                            </template>
                        </div>

                        <div class="space-y-3" x-show="selectedEntry.attachments.length > 0">
                            <flux:heading size="sm">{{ __('Attachments') }}</flux:heading>

                            <template x-for="attachment in selectedEntry.attachments" :key="attachment.url">
                                <a
                                    x-bind:href="attachment.url"
                                    target="_blank"
                                    rel="noreferrer"
                                    class="flex items-center justify-between rounded-xl border border-zinc-200 bg-zinc-50/70 px-4 py-3 text-sm text-zinc-900 hover:bg-zinc-100 dark:border-zinc-700 dark:bg-zinc-900/40 dark:text-zinc-100 dark:hover:bg-zinc-900"
                                >
                                    <span class="truncate" x-text="attachment.name"></span>
                                    <span class="text-sky-700 dark:text-sky-300">{{ __('Open') }}</span>
                                </a>
                            </template>
                        </div>

                        <div class="flex items-center justify-start">
                            <a
                                x-bind:href="selectedEntry.source_url"
                                class="inline-flex items-center rounded-lg bg-zinc-900 px-4 py-2 text-sm font-medium text-white dark:bg-zinc-100 dark:text-zinc-900"
                            >
                                <span x-text="selectedEntry.source_label"></span>
                            </a>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </section>
</x-layouts::app>
