import { fileURLToPath } from 'node:url';
import { defineConfig } from 'vitest/config';

/*
 * Configuration des specs Vitest des composables front (graphe du builder,
 * sauvegarde debouncée, simulation d'exécution). Hors build applicatif :
 * ce fichier et les specs sont exclus du tsconfig (npm run types:check),
 * l'outillage de test JS n'est pas encore une dépendance persistée.
 *
 * Commande : npx vitest run --config resources/js/vitest.config.ts
 */
export default defineConfig({
    test: {
        environment: 'node',
        include: [
            'resources/js/composables/__tests__/**/*.spec.ts',
            'resources/js/routes/__tests__/**/*.spec.ts',
        ],
    },
    resolve: {
        alias: {
            '@': fileURLToPath(new URL('./', import.meta.url)),
        },
    },
});
