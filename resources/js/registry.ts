/// <reference types="@lattice-php/lattice/vite-client" />
import { extendRegistry, registry as packageRegistry } from "@lattice-php/lattice";
import { DEFAULT_NAMESPACE, UI_NAMESPACE } from "@lattice-php/ui/i18n/instance";
import plugins from "virtual:lattice/plugins";

export const registry = extendRegistry(packageRegistry, ...plugins, {
    components: {},
    name: "app",
});

/**
 * Derived from the discovered plugins, so a newly installed package's strings do
 * not silently render in English while the rest of the page is translated.
 */
export const i18nNamespaces = [
    DEFAULT_NAMESPACE,
    UI_NAMESPACE,
    ...plugins.flatMap((plugin) => plugin.i18n?.namespace ?? []),
];
