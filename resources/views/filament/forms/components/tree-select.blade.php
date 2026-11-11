@php
    $nodes = $getTreeNodes();
    $paths = $getTreeOptionPaths($nodes);
    $statePath = $getStatePath();
    $id = $getId();
@endphp

<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div
        wire:key="{{ $getLivewireKey() }}.{{ md5(serialize([$nodes, $isMultiple(), $isDisabled()])) }}"
        x-data="{
            state: $wire.{{ $applyStateBindingModifiers('$entangle('.\Illuminate\Support\Js::from($statePath).')') }},
            paths: @js((object) $paths),
            multiple: @js($isMultiple()),
            collapsed: Object.create(null),
            visible: null,
            get selected() {
                const values = this.multiple ? this.state : [this.state]
                return Array.isArray(values) ? values.filter(value => value !== null && value !== '').map(String) : []
            },
            get selectedPaths() {
                return this.selected.filter(value => Object.hasOwn(this.paths, value)).map(value => this.paths[value])
            },
            select(value, checked) {
                if (this.multiple) {
                    this.state = checked ? [...new Set([...this.selected, value])] : this.selected.filter(id => id !== value)
                } else {
                    this.state = value
                    this.close(true)
                }
            },
            clear() {
                this.state = this.multiple ? [] : null
            },
            close(focus = false) {
                this.$refs.panel.hidePopover()
                if (focus) this.$refs.trigger.focus()
            },
            positionPanel() {
                const panel = this.$refs.panel
                if (!panel.matches(':popover-open')) return

                const trigger = this.$refs.trigger.getBoundingClientRect()
                const viewportHeight = document.documentElement.clientHeight
                const viewportPadding = 8
                const below = viewportHeight - trigger.bottom - viewportPadding
                const above = trigger.top - viewportPadding

                panel.style.width = `${trigger.width}px`
                panel.style.left = `${trigger.left}px`

                const opensUp = panel.scrollHeight > below && above > below
                panel.style.maxHeight = `${Math.max(0, opensUp ? above : below)}px`
                panel.style.top = opensUp ? 'auto' : `${trigger.bottom}px`
                panel.style.bottom = opensUp ? `${viewportHeight - trigger.top}px` : 'auto'
            },
            filter(query) {
                const term = query.trim().toLocaleLowerCase()
                if (!term) {
                    this.visible = null
                    return
                }

                const visible = new Set()
                for (const option of this.$refs.list.querySelectorAll('[data-tree-value]')) {
                    if (!this.paths[option.dataset.treeValue]?.join(' / ').toLocaleLowerCase().includes(term)) continue
                    for (let node = option; node && !visible.has(node.dataset.treeValue); node = node.parentElement.closest('[data-tree-value]')) {
                        visible.add(node.dataset.treeValue)
                    }
                }
                this.visible = visible
            },
        }"
        @unless ($isDisabled())
            x-on:scroll.window.capture.throttle.16ms="positionPanel()"
            x-on:resize.window="positionPanel()"
            x-on:keydown.escape="if ($refs.panel.matches(':popover-open')) { $event.preventDefault(); $event.stopPropagation(); close(true) }"
        @endunless
        {{ $getExtraAttributeBag() }}
    >
        <x-filament::input.wrapper :disabled="$isDisabled()" :valid="! $errors->has($statePath)">
            <button
                id="{{ $id }}"
                type="button"
                x-ref="trigger"
                @unless ($isDisabled())
                    popovertarget="{{ $id }}-options"
                @endunless
                @disabled($isDisabled())
                class="flex min-h-9 w-full min-w-0 items-center justify-between gap-2 px-3 py-1.5 text-start text-sm text-gray-950 disabled:text-gray-500 dark:text-white dark:disabled:text-gray-400"
            >
                <span class="min-w-0 truncate" x-bind:title="selectedPaths.map(path => path.join(' › ')).join('، ')">
                    <span x-show="selectedPaths.length === 0">{{ $getPlaceholder() ?? 'انتخاب کنید' }}</span>
                    <template x-for="(path, pathIndex) in selectedPaths" x-bind:key="pathIndex">
                        <span>
                            <span x-show="pathIndex > 0">، </span>
                            <template x-for="(label, index) in path" x-bind:key="index">
                                <span>
                                    <span x-show="index > 0" aria-hidden="true" class="fi-tree-select-path-separator inline-block align-middle">
                                        <x-filament::icon icon="heroicon-m-chevron-left" class="size-4" />
                                    </span>
                                    <span x-text="label"></span>
                                </span>
                            </template>
                        </span>
                    </template>
                </span>
                <x-filament::icon icon="heroicon-o-chevron-down" class="size-4 shrink-0 text-gray-500" />
            </button>
        </x-filament::input.wrapper>

        @unless ($isDisabled())
            <div
                id="{{ $id }}-options"
                popover
                x-ref="panel"
                x-on:toggle="positionPanel()"
                class="fixed inset-auto m-0 space-y-2 overflow-y-auto overscroll-contain rounded-lg bg-white p-2 shadow-lg ring-1 ring-gray-950/10 dark:bg-gray-900 dark:ring-white/15"
            >
                @if ($isSearchable() && $nodes !== [])
                    <x-filament::input.wrapper>
                        <x-filament::input
                            type="search"
                            aria-label="جست‌وجو در گزینه‌ها"
                            placeholder="جست‌وجو در گزینه‌ها"
                            x-on:input="filter($event.target.value)"
                            x-on:keydown.enter.prevent
                        />
                    </x-filament::input.wrapper>
                @endif

                <div x-ref="list" role="group" aria-label="{{ $getLabel() }}" class="max-h-52 overflow-y-auto">
                    @include('filament.forms.components.tree-select-options', ['nodes' => $nodes, 'fieldId' => $id, 'multiple' => $isMultiple()])

                    <p x-show="visible !== null && visible.size === 0" class="px-2 py-1 text-sm text-gray-500 dark:text-gray-400">گزینه‌ای پیدا نشد.</p>
                    @if ($nodes === [])
                        <p class="px-2 py-1 text-sm text-gray-500 dark:text-gray-400">گزینه‌ای برای انتخاب وجود ندارد.</p>
                    @endif
                </div>

                <button
                    type="button"
                    x-show="selected.length > 0"
                    x-on:click="clear()"
                    class="rounded px-2 py-1 text-sm text-gray-600 hover:bg-gray-100 focus-visible:outline-2 focus-visible:outline-primary-500 dark:text-gray-300 dark:hover:bg-white/10"
                >
                    پاک کردن انتخاب
                </button>
            </div>
        @endunless
    </div>
</x-dynamic-component>
