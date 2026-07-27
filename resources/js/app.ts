import 'vuetify/styles';
import '../css/app.css';
import { createApp, h } from 'vue';
import type { DefineComponent } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { createVuetify } from 'vuetify';
import * as components from 'vuetify/components';
import * as directives from 'vuetify/directives';
import { aliases, mdi } from 'vuetify/iconsets/mdi';

const vuetify = createVuetify({
    components,
    directives,
    icons: { defaultSet: 'mdi', aliases, sets: { mdi } },
    theme: {
        defaultTheme: 'studioLemus',
        themes: {
            studioLemus: {
                dark: false,
                colors: {
                    primary: '#682447',
                    'primary-darken-1': '#49203A',
                    secondary: '#C98B9A',
                    background: '#F7F7F8',
                    surface: '#FFFFFF',
                    'surface-variant': '#F1EFF2',
                    'on-background': '#30252C',
                    'on-surface': '#30252C',
                    'on-surface-variant': '#62545D',
                    success: '#4C795E',
                    warning: '#A8643B',
                    error: '#B2393A',
                    info: '#6A6077',
                },
            },
        },
    },
    defaults: {
        VBtn: { rounded: 'lg', height: 44 },
        VCard: { rounded: 'xl', elevation: 0 },
        VTextField: { variant: 'outlined', color: 'primary', baseColor: '#62545D' },
        VTextarea: { variant: 'outlined', color: 'primary', baseColor: '#62545D' },
        VSelect: { variant: 'outlined', color: 'primary', baseColor: '#62545D' },
        VAutocomplete: { variant: 'outlined', color: 'primary', baseColor: '#62545D' },
        VDialog: { scrollable: true },
    },
});

createInertiaApp({
    resolve: name => {
        const pages = import.meta.glob('./Pages/**/*.vue', { eager: true }) as Record<string, { default: DefineComponent }>;
        return pages[`./Pages/${name}.vue`];
    },
    setup({ el, App, props, plugin }) { createApp({ render: () => h(App, props) }).use(plugin).use(vuetify).mount(el); },
});
