{{--
    A copy of the table's horizontal scrollbar above the rows, kept in sync
    with the real one (.fi-ta-content-ctn) so a wide table can be scrolled
    without first going to the bottom of the page. Only shown while the table
    overflows. Scroll events are caught on the table container in the capture
    phase, so this keeps working when Livewire re-renders or swaps the content
    element; wire:ignore keeps Livewire from resetting this element itself.
--}}
<div
    wire:ignore
    x-cloak
    x-show="overflow"
    x-data="{
        overflow: false,
        observers: [],
        content() {
            return this.$root.closest('.fi-ta-ctn')?.querySelector('.fi-ta-content-ctn')
        },
        measure() {
            const content = this.content()
            this.overflow = !! content && content.scrollWidth > content.clientWidth + 1
            if (! content) return
            this.$refs.spacer.style.width = content.scrollWidth + 'px'
            this.$root.scrollLeft = content.scrollLeft
        },
        init() {
            const table = this.$root.closest('.fi-ta-ctn')
            if (! table) return

            let frame = null
            const remeasure = () => {
                cancelAnimationFrame(frame)
                frame = requestAnimationFrame(() => this.measure())
            }

            table.addEventListener('scroll', (event) => {
                if (event.target.classList?.contains('fi-ta-content-ctn') && Math.abs(this.$root.scrollLeft - event.target.scrollLeft) > 1) {
                    this.$root.scrollLeft = event.target.scrollLeft
                }
            }, true)

            const mutations = new MutationObserver(remeasure)
            mutations.observe(table, { childList: true, subtree: true })
            const resizes = new ResizeObserver(remeasure)
            resizes.observe(table)
            this.observers = [mutations, resizes]

            remeasure()
        },
        destroy() {
            this.observers.forEach((observer) => observer.disconnect())
        },
    }"
    x-on:scroll="
        const ctn = content()
        if (ctn && Math.abs(ctn.scrollLeft - $el.scrollLeft) > 1) ctn.scrollLeft = $el.scrollLeft
    "
    class="fi-ta-top-scroll"
    style="overflow-x: auto; overflow-y: hidden;"
>
    <div x-ref="spacer" style="height: 1px;"></div>
</div>
