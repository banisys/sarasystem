{{-- Whether this user's modals open full screen (ADR 0060), shared by every modal on the page. --}}
<script>
    document.addEventListener('alpine:init', () => {
        const storageKey = @json('admin-modal-fullscreen.'.auth()->id());

        window.Alpine.store('adminModal', {
            isFullscreen: localStorage.getItem(storageKey) === 'true',

            toggle() {
                this.isFullscreen = ! this.isFullscreen;
                localStorage.setItem(storageKey, this.isFullscreen);
            },
        });
    });
</script>
