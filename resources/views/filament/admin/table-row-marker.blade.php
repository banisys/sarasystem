{{-- Remember the last row touched without interfering with text selection or row actions. --}}
<span
    hidden
    x-data="{
        rowKey: null,
        observer: null,
        table() {
            return this.$root.closest('.fi-ta-ctn')
        },
        mark(event) {
            if (event.button !== 0) return
            const row = event.target.closest?.('.fi-ta-row[wire\\:key]')
            if (! row || row.closest('.fi-ta-ctn') !== this.table()) return
            this.rowKey = row.getAttribute('wire:key')
            this.restore()
        },
        restore() {
            this.table()?.querySelectorAll('.fi-ta-row[wire\\:key]').forEach((row) => {
                if (row.closest('.fi-ta-ctn') !== this.table()) return
                const marked = row.getAttribute('wire:key') === this.rowKey
                if (marked && ! row.hasAttribute('data-clicked-row')) {
                    row.setAttribute('data-clicked-row', '')
                } else if (! marked && row.hasAttribute('data-clicked-row')) {
                    row.removeAttribute('data-clicked-row')
                }
            })
        },
        init() {
            this.observer = new MutationObserver(() => this.restore())
            this.observer.observe(this.table(), {
                childList: true,
                subtree: true,
                attributes: true,
                attributeFilter: ['data-clicked-row'],
            })
        },
        destroy() {
            this.observer?.disconnect()
        },
    }"
    x-on:pointerdown.window.capture="mark($event)"
    x-on:click.window.capture="mark($event)"
></span>
