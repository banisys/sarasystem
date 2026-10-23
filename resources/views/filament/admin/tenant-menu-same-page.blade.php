{{--
    The tenant menu links to each tenant's dashboard. Just before a link is
    followed (pointer or keyboard), it is pointed at the switch-tenant route
    instead, with the current page's path inside the panel, so the user stays
    on the same page in the other tenant (SwitchPanelTenantController).
--}}
<script>
    (() => {
        const retarget = (event) => {
            const link = event.target.closest?.('.fi-tenant-menu-item a[href]')
            if (! link) {
                return
            }

            link.dataset.tenantHome ??= new URL(link.href, location.origin).pathname
            const home = link.dataset.tenantHome
            const panelPath = home.slice(0, home.lastIndexOf('/'))

            if (! location.pathname.startsWith(panelPath + '/')) {
                return
            }

            // The path after the current tenant's slug, e.g. "users" or "users/5/edit".
            const page = location.pathname.slice(panelPath.length + 1).split('/').slice(1).join('/')

            link.setAttribute('href', page ? home + '/switch-tenant?page=' + encodeURIComponent(page) : home)
        }

        document.addEventListener('pointerdown', retarget, true)
        document.addEventListener('keydown', retarget, true)
        document.addEventListener('click', retarget, true)
    })()
</script>
