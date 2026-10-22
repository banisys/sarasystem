import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { ZiggyVue } from 'ziggy-js';

const appName = import.meta.env.VITE_APP_NAME || 'SaraSystem';

const appPages = import.meta.glob('./Pages/**/*.vue');
const modulePages = import.meta.glob('../../Modules/*/resources/js/Pages/**/*.vue');

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    // "Welcome" is resources/js/Pages/Welcome.vue; "Exam::Attempts/Show" is a module page,
    // Modules/Exam/resources/js/Pages/Attempts/Show.vue (docs/decisions/0005-modules.md).
    resolve: (name) => {
        const [module, page] = name.includes('::') ? name.split('::') : [null, name];
        const path = module ? `../../Modules/${module}/resources/js/Pages/${page}.vue` : `./Pages/${page}.vue`;
        const loader = (module ? modulePages : appPages)[path];

        if (!loader) {
            throw new Error(`Inertia page [${name}] not found at ${path}.`);
        }

        return loader();
    },
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .mount(el);
    },
    progress: {
        color: '#7c3aed',
    },
});
