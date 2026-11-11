<ul class="space-y-1">
    @foreach ($nodes as $node)
        @php($value = (string) $node['id'])
        <li data-tree-value="{{ $value }}" x-show="visible === null || visible.has(@js($value))">
            <div class="flex min-w-0 items-start gap-1">
                @if (! empty($node['children']))
                    <button
                        type="button"
                        aria-label="باز یا بسته کردن زیرشاخه‌های {{ $node['label'] }}"
                        x-bind:aria-expanded="(visible !== null || !collapsed[@js($value)]).toString()"
                        x-bind:disabled="visible !== null"
                        x-on:click="collapsed[@js($value)] = !collapsed[@js($value)]"
                        class="mt-0.5 grid size-7 shrink-0 place-items-center rounded text-gray-500 hover:bg-gray-100 focus-visible:outline-2 focus-visible:outline-primary-500 dark:text-gray-400 dark:hover:bg-white/10"
                    >
                        <span x-bind:class="{ '-rotate-90 rtl:rotate-90': visible === null && collapsed[@js($value)] }">
                            <x-filament::icon icon="heroicon-o-chevron-down" class="size-4" />
                        </span>
                    </button>
                @else
                    <span class="w-7 shrink-0" aria-hidden="true"></span>
                @endif

                <label class="flex min-w-0 grow cursor-pointer items-start gap-2 rounded px-2 py-1.5 text-sm text-gray-950 hover:bg-gray-100 dark:text-white dark:hover:bg-white/10">
                    <input
                        type="{{ $multiple ? 'checkbox' : 'radio' }}"
                        name="{{ $fieldId }}-choice"
                        value="{{ $value }}"
                        x-bind:checked="selected.includes($el.value)"
                        x-on:change="select($event.target.value, $event.target.checked)"
                        class="mt-0.5 size-4 shrink-0 accent-primary-600 focus:ring-primary-500"
                    />
                    <span class="min-w-0 wrap-anywhere">{{ $node['label'] }}</span>
                </label>
            </div>

            @if (! empty($node['children']))
                <div x-show="visible !== null || !collapsed[@js($value)]" class="ms-4 border-s border-gray-200 ps-1 dark:border-white/10">
                    @include('filament.forms.components.tree-select-options', ['nodes' => $node['children'], 'fieldId' => $fieldId, 'multiple' => $multiple])
                </div>
            @endif
        </li>
    @endforeach
</ul>
