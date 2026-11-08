{{-- Mark a row by pointer or arrow keys without interfering with text selection or controls. --}}
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
            if (! row || row.closest('.fi-ta-ctn') !== this.table()) {
                this.table()?.removeAttribute('data-row-navigation-active')
                return
            }
            this.table().setAttribute('data-row-navigation-active', '')
            this.rowKey = row.getAttribute('wire:key')
            this.restore()
        },
        navigate(event) {
            if (! ['ArrowUp', 'ArrowDown'].includes(event.key)) return
            if (event.defaultPrevented || event.isComposing || event.altKey || event.ctrlKey || event.metaKey || event.shiftKey) return
            const table = this.table()
            if (! table?.hasAttribute('data-row-navigation-active')) return
            if (event.target.closest?.('input, textarea, select, button, a, [contenteditable]:not([contenteditable=false]), [role=combobox], [role=textbox], [role=menu], [role=dialog], dialog')) return
            if (Array.from(document.querySelectorAll('.fi-modal, .fi-dropdown-panel')).some((panel) => panel.checkVisibility())) return
            const rows = Array.from(table.querySelectorAll('.fi-ta-row[wire\\:key]'))
                .filter((row) => row.closest('.fi-ta-ctn') === table && row.checkVisibility())
            if (! rows.length) return
            const current = rows.findIndex((row) => row.getAttribute('wire:key') === this.rowKey)
            const next = current === -1
                ? (event.key === 'ArrowDown' ? 0 : rows.length - 1)
                : Math.max(0, Math.min(rows.length - 1, current + (event.key === 'ArrowDown' ? 1 : -1)))
            event.preventDefault()
            this.rowKey = rows[next].getAttribute('wire:key')
            this.restore()
            rows[next].setAttribute('tabindex', '-1')
            rows[next].focus({ preventScroll: true })
            rows[next].scrollIntoView({ block: 'nearest', inline: 'nearest', behavior: 'instant' })
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
    x-on:keydown.window="navigate($event)"
></span>
