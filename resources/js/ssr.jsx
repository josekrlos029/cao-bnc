import { createInertiaApp } from '@inertiajs/react';
import { renderToString } from 'react-dom/server';
import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';

export default function render(page) {
    return createInertiaApp({
        page,
        render: renderToString,
        title: (title) => `${title} - ${import.meta.env.VITE_APP_NAME || 'Laravel'}`,
        resolve: (name) => resolvePageComponent(`./Pages/${name}.jsx`, import.meta.glob('./Pages/**/*.jsx')),
        setup({ App, props }) {
            return <App {...props} />;
        },
    });
}
