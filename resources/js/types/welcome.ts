import type { Component } from 'vue';

/*
 * Types de la page d'accueil « welcome » (maquette welcome.html) :
 * démo « Exécution en direct », carte observabilité (graphe + flux live).
 * Miroir des données simulées de la maquette — aucune prop backend.
 */

/** Clés des nodes de la démo « Exécution en direct ». */
export type WelcomeDemoNodeId = 'webhook' | 'ai' | 'cond' | 'sort' | 'mail';

/** Clés des arêtes de la démo (ab/bc = ligne principale, ce/cd = branches). */
export type WelcomeDemoEdgeKey = 'ab' | 'bc' | 'ce' | 'cd';

/** États visuels d'un node de la démo. */
export type WelcomeDemoNodeState = 'pending' | 'running' | 'done';

/** États visuels d'une arête de la démo. */
export type WelcomeDemoEdgeState = 'idle' | 'live' | 'done';

/** Étapes du journal simulé de la démo. */
export type WelcomeDemoLogKey =
    | 'idle'
    | 'webhook'
    | 'ai'
    | 'cond'
    | 'mail'
    | 'sort'
    | 'done';

/** Node de la démo, positionné dans le viewBox 660×280 du canvas. */
export type WelcomeDemoNode = {
    id: WelcomeDemoNodeId;
    x: number;
    y: number;
    w: number;
    h: number;
    /** Couleur catégorielle (token CSS --cat-N). */
    color: string;
    icon: Component;
    label: string;
    sub: string;
};

/** Arête polyligne de la démo : chemin SVG + points pour l'animation du paquet. */
export type WelcomeDemoEdge = {
    d: string;
    color: string;
    pts: Array<[number, number]>;
};

/** Item du flux d'activité simulé de la carte observabilité. */
export type WelcomeFeedItem = {
    id: number;
    icon: Component;
    color: string;
    text: string;
    time: string;
};
