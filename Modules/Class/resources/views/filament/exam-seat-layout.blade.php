<div
    x-data="{
        layout: $wire.$entangle(@js($getStatePath())),
        attendees: @js($attendees),
        selected: null,
        dragging: null,
        get names() {
            return Object.fromEntries(this.attendees.map(({ id, name }) => [id, name]));
        },
        get unassigned() {
            const assigned = new Set(this.layout.seats);
            return this.attendees.filter(({ id }) => !assigned.has(id));
        },
        resize(dimension, input, limit) {
            const value = Math.min(limit, Math.max(1, Math.trunc(Number(input.value)) || 1));
            const rows = dimension === 'rows' ? value : this.layout.rows;
            const columns = dimension === 'columns' ? value : this.layout.columns;
            const seats = Array.from({ length: rows * columns }, (_, index) => {
                const row = Math.floor(index / columns);
                const column = index % columns;
                return row < this.layout.rows && column < this.layout.columns
                    ? this.layout.seats[row * this.layout.columns + column] : null;
            });
            this.layout = { rows, columns, seats };
            input.value = value;
        },
        choose(index) {
            if (this.selected !== null) {
                this.place(this.selected, index);
            } else {
                this.selected = this.layout.seats[index];
            }
        },
        place(id, index) {
            if (!Object.hasOwn(this.names, id)) return;
            const seats = [...this.layout.seats];
            const origin = seats.indexOf(id);
            if (origin !== -1) seats[origin] = seats[index];
            seats[index] = id;
            this.layout = { ...this.layout, seats };
            this.selected = this.dragging = null;
        },
        remove(id) {
            this.layout = { ...this.layout, seats: this.layout.seats.map(seat => seat === id ? null : seat) };
            this.selected = this.dragging = null;
        },
        startDrag(event, id) {
            if (id === null) return;
            this.dragging = id;
            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('text/plain', String(id));
        },
        fill() {
            this.layout = { ...this.layout, seats: this.layout.seats.map((_, index) => this.attendees[index]?.id ?? null) };
            this.selected = null;
        },
        number(value) {
            return new Intl.NumberFormat('fa-IR', { useGrouping: false }).format(value);
        },
    }"
    class="space-y-4"
>
    <p class="text-sm text-gray-600 dark:text-gray-300">
        نام یک نفر یا صندلی او را انتخاب کنید، سپس صندلی مقصد را بزنید یا نام او را روی آن بکشید. اگر هر دو صندلی پر باشند، جای دو نفر عوض می‌شود.
    </p>

    <div class="flex flex-wrap items-end gap-3">
        <label class="flex flex-col gap-1 text-sm">
            تعداد ردیف‌ها
            <x-filament::input.wrapper>
                <x-filament::input type="number" min="1" :max="$maxRows" x-bind:value="layout.rows" x-on:change="resize('rows', $el, {{ $maxRows }})" />
            </x-filament::input.wrapper>
        </label>
        <label class="flex flex-col gap-1 text-sm">
            صندلی در هر ردیف
            <x-filament::input.wrapper>
                <x-filament::input type="number" min="1" :max="$maxColumns" x-bind:value="layout.columns" x-on:change="resize('columns', $el, {{ $maxColumns }})" />
            </x-filament::input.wrapper>
        </label>
        <x-filament::button type="button" color="gray" x-on:click="fill()" x-bind:disabled="attendees.length === 0">
            چیدمان خودکار
        </x-filament::button>
    </div>

    <p class="min-h-6 text-sm text-gray-700 dark:text-gray-200" aria-live="polite">
        <span x-text="selected === null ? 'نفر یا صندلی را انتخاب کنید.' : 'انتخاب شده: ' + names[selected]"></span>
        <button type="button" class="ms-2 underline" x-show="selected !== null" x-on:click="selected = null">لغو انتخاب</button>
        <button type="button" class="ms-2 underline" x-show="selected !== null && layout.seats.includes(selected)" x-on:click="remove(selected)">خالی کردن صندلی</button>
    </p>

    <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_14rem]">
        <div class="min-w-0 overflow-auto rounded-xl border border-gray-200 p-3 dark:border-white/10">
            <div class="mb-3 rounded-lg bg-primary-50 p-2 text-center text-sm text-primary-700 dark:bg-primary-500/15 dark:text-primary-300">
                جلوی سالن · جای مراقبان
            </div>
            <div class="grid gap-2" x-bind:style="{ gridTemplateColumns: 'repeat(' + layout.columns + ', minmax(6.5rem, 1fr))' }">
                <template x-for="(id, index) in layout.seats" x-bind:key="index">
                    <button
                        type="button"
                        class="flex min-h-16 flex-col items-center justify-center rounded-lg border px-2 py-1 text-sm focus-visible:outline-2 focus-visible:outline-primary-500 aria-pressed:ring-2 aria-pressed:ring-primary-500"
                        x-bind:class="id === null ? 'border-dashed border-gray-300 text-gray-500 dark:border-white/20 dark:text-gray-400' : 'border-primary-200 bg-primary-50 text-primary-800 dark:border-primary-400/30 dark:bg-primary-500/15 dark:text-primary-200'"
                        x-bind:aria-pressed="id !== null && selected === id"
                        x-bind:draggable="id !== null"
                        x-on:click="choose(index)"
                        x-on:dragstart="startDrag($event, id)"
                        x-on:dragend="dragging = null"
                        x-on:dragover.prevent
                        x-on:drop.prevent="dragging !== null && place(dragging, index)"
                    >
                        <span class="text-xs" x-text="'صندلی ' + number(index + 1)"></span>
                        <span class="wrap-anywhere font-medium" x-text="id === null ? 'خالی' : names[id]"></span>
                    </button>
                </template>
            </div>
        </div>

        <div class="rounded-xl border border-gray-200 p-3 dark:border-white/10" x-on:dragover.prevent x-on:drop.prevent="dragging !== null && remove(dragging)">
            <h3 class="mb-2 text-sm font-medium" x-text="'بدون صندلی (' + number(unassigned.length) + ')'"></h3>
            <div class="flex max-h-80 flex-wrap gap-2 overflow-auto">
                <template x-for="person in unassigned" x-bind:key="person.id">
                    <button type="button" draggable="true" class="rounded-lg bg-gray-100 px-2 py-1 text-start text-sm aria-pressed:ring-2 aria-pressed:ring-primary-500 dark:bg-white/10"
                        x-bind:aria-pressed="selected === person.id"
                        x-on:click="selected = selected === person.id ? null : person.id"
                        x-on:dragstart="startDrag($event, person.id)"
                        x-on:dragend="dragging = null"
                        x-text="person.name"
                    ></button>
                </template>
            </div>
            <p class="text-xs text-gray-500 dark:text-gray-400" x-show="unassigned.length === 0">همهٔ شرکت‌کنندگان صندلی دارند.</p>
        </div>
    </div>
</div>
