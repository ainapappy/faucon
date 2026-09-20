<script setup lang="ts">
import { Skeleton } from '@/components/ui/skeleton';

/*
 * Squelettes de la zone différée `analytics` (phase 10, D7) — même grille
 * que le rendu final (graphe + colonne droite) pour éviter tout saut de
 * layout pendant la résolution de la prop DEFER.
 */
</script>

<template>
    <div class="grid gap-3.5 lg:grid-cols-[minmax(0,1fr)_340px]">
        <!-- Squelette du graphe -->
        <div class="bg-card rounded-lg border p-4.5 shadow-xs">
            <div class="flex items-center justify-between gap-3">
                <div class="space-y-2">
                    <Skeleton class="h-4 w-24" />
                    <Skeleton class="h-3 w-40" />
                </div>
                <Skeleton class="h-9 w-40 rounded-md" />
            </div>
            <Skeleton class="mt-4 h-56 w-full" />
        </div>

        <!-- Squelette de la colonne droite (2 cartes empilées) -->
        <div class="grid content-start gap-3.5">
            <div class="bg-card rounded-lg border p-4.5 shadow-xs">
                <Skeleton class="mb-3 h-4 w-44" />
                <div class="grid gap-2.5">
                    <Skeleton
                        v-for="index in 4"
                        :key="index"
                        class="h-8 w-full"
                    />
                </div>
            </div>
            <div class="bg-card rounded-lg border p-4.5 shadow-xs">
                <Skeleton class="mb-3 h-4 w-28" />
                <div class="grid gap-1">
                    <Skeleton
                        v-for="index in 5"
                        :key="index"
                        class="h-9 w-full rounded-md"
                    />
                </div>
            </div>
        </div>
    </div>
</template>
