{{-- Some controls explicitly call reportValidity(), which ignores the form's novalidate. --}}
<script nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">
    (() => {
        if (window.saraAdminNativeValidationDisabled) {
            return
        }

        window.saraAdminNativeValidationDisabled = true

        document.addEventListener('invalid', (event) => {
            if (! document.body.classList.contains('fi-panel-admin')) {
                return
            }

            event.preventDefault()
            event.stopImmediatePropagation()
        }, true)
    })()
</script>
