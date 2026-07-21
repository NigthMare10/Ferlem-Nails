import 'vuetify/styles';
import '../css/app.css';
import { createApp, h } from 'vue';
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
                    primary: '#8A455F',
                    'primary-darken-1': '#703247',
                    secondary: '#D8A6B5',
                    background: '#F8F4F3',
                    surface: '#FFFCFB',
                    'surface-variant': '#F0E8E7',
                    'on-background': '#332D2F',
                    'on-surface': '#332D2F',
                    'on-surface-variant': '#706669',
                    success: '#47735B',
                    warning: '#A66A16',
                    error: '#B3263E',
                    info: '#596B85',
                },
            },
        },
    },
    defaults: {
        VBtn: { rounded: 'lg', height: 44 },
        VCard: { rounded: 'xl', elevation: 0 },
        VTextField: { variant: 'outlined', color: 'primary' },
        VTextarea: { variant: 'outlined', color: 'primary' },
        VSelect: { variant: 'outlined', color: 'primary' },
        VDialog: { scrollable: true },
    },
});

createInertiaApp({
    resolve: name => {
        const pages = import.meta.glob('./Pages/**/*.vue', { eager: true }) as Record<string, { default: unknown }>;
        return pages[`./Pages/${name}.vue`];
    },
    setup({ el, App, props, plugin }) { createApp({ render: () => h(App, props) }).use(plugin).use(vuetify).mount(el); },
});
