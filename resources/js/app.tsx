/// <reference types="@lattice-php/lattice/svg-sprite-client" />
import { createInertiaApp } from "@inertiajs/react";
import {
    createLayoutResolver,
    createPageResolver,
    initializeAppearance,
    Provider,
    withVisitHeaders,
} from "@lattice-php/lattice";
import { configureI18nFromPageProps, LocaleReload } from "@lattice-php/ui/i18n";
import { StrictMode } from "react";
import { createRoot } from "react-dom/client";
import sprite from "virtual:svg-sprite";
import { i18nNamespaces, registry } from "@/registry";

const applicationName = import.meta.env.VITE_APP_NAME || "Lock";

void createInertiaApp({
    title: (title) => (title ? `${title} - ${applicationName}` : applicationName),
    resolve: createPageResolver({}),
    layout: createLayoutResolver(),
    progress: {
        color: "#4B5563",
    },
    defaults: {
        visitOptions: withVisitHeaders,
    },
    setup({ el, App, props }) {
        if (!el) {
            return;
        }

        const root = createRoot(el);
        const render = () =>
            root.render(
                <StrictMode>
                    <Provider registry={registry} sprite={sprite}>
                        <App {...props} />
                        <LocaleReload />
                    </Provider>
                </StrictMode>,
            );

        void configureI18nFromPageProps(props.initialPage.props, {
            namespaces: i18nNamespaces,
        }).then(render, render);
    },
});

initializeAppearance();
