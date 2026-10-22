{{--
    Remembers, per page, whether a table's collapsible filters were left open
    (Filament keeps it only in the table's Alpine state, areFiltersOpen), so
    the filters reopen after a reload or when the page is visited again. The
    state lives in the viewer's localStorage, keyed by path and table order.
--}}
<script>
    (() => {
        const bind = () => {
            if (! window.Alpine) {
                return
            }

            document.querySelectorAll('.fi-ta').forEach((table, index) => {
                if (table.dataset.filtersOpenPersisted) {
                    return
                }

                // Only the collapsible "above content" filters: the dropdown layout also needs positioning.
                if (! table.querySelector('.fi-ta-filters-above-content-ctn .fi-ta-filters-trigger-action-ctn')) {
                    return
                }

                const data = Alpine.$data(table)
                if (! data || ! ('areFiltersOpen' in data)) {
                    return
                }

                table.dataset.filtersOpenPersisted = '1'
                const key = 'fi-filters-open:' + location.pathname + ':' + index

                try {
                    if (localStorage.getItem(key) === '1') {
                        data.areFiltersOpen = true
                    }
                } catch (e) {}

                Alpine.effect(() => {
                    const open = data.areFiltersOpen

                    try {
                        open ? localStorage.setItem(key, '1') : localStorage.removeItem(key)
                    } catch (e) {}
                })
            })
        }

        document.addEventListener('alpine:initialized', bind)
        document.addEventListener('livewire:navigated', () => setTimeout(bind))
        document.addEventListener('livewire:init', () => {
            Livewire.hook('commit', ({ succeed }) => succeed(() => setTimeout(bind)))
        })
    })()
</script>
