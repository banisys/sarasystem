import { computed, onMounted, onUnmounted, ref } from 'vue';

const storageKey = 'sarasystem:profile-theme';
const savedPreference = ref(null);
const validTheme = (value) => value === 'dark' || value === 'light' ? value : null;

export function useProfileTheme() {
    const device = typeof window === 'undefined' ? null : window.matchMedia('(prefers-color-scheme: dark)');
    const systemDark = ref(device?.matches ?? false);
    const theme = computed(() => savedPreference.value ?? (systemDark.value ? 'dark' : 'light'));

    if (device) {
        try {
            savedPreference.value = validTheme(window.localStorage.getItem(storageKey));
        } catch {
            // The preference can still be used in this tab when storage is unavailable.
        }
    }

    function syncSystem(event) {
        systemDark.value = event.matches;
    }

    function syncPreference(event) {
        if (event.key === storageKey || event.key === null) {
            savedPreference.value = validTheme(event.newValue);
        }
    }

    function toggleTheme() {
        savedPreference.value = theme.value === 'dark' ? 'light' : 'dark';
        try {
            window.localStorage.setItem(storageKey, savedPreference.value);
        } catch {
            // Keep the tab's current selection.
        }
    }

    onMounted(() => {
        device?.addEventListener('change', syncSystem);
        window.addEventListener('storage', syncPreference);
    });
    onUnmounted(() => {
        device?.removeEventListener('change', syncSystem);
        window.removeEventListener('storage', syncPreference);
    });

    return { theme, toggleTheme };
}
