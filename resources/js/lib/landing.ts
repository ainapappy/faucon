/*
 * Helpers purs de la page d'accueil « welcome » (maquette welcome.html) :
 * transposés des helpers de la maquette ($fmt.number, $linePath, $areaPath)
 * et de son garde « mouvement réduit ». Aucun état réactif ici —
 * les machines animées vivent dans composables/useWelcome*.
 */

const numberFormatter = new Intl.NumberFormat('fr-FR');

/** Formatage FR des chiffres clés (maquette : $fmt.number). */
export function formatLandingNumber(value: number): string {
    return numberFormatter.format(value);
}

/**
 * Chemin SVG « ligne » normalisé dans une boîte width×height
 * (maquette : $linePath).
 */
export function landingLinePath(
    points: number[],
    width: number,
    height: number,
    { pad = 4 }: { pad?: number } = {},
): string {
    if (points.length === 0) {
        return '';
    }

    const min = Math.min(...points);
    const max = Math.max(...points);
    const range = max - min || 1;
    const stepX = (width - pad * 2) / (points.length - 1 || 1);

    return points
        .map((point, index) => {
            const x = pad + index * stepX;
            const y = pad + (1 - (point - min) / range) * (height - pad * 2);

            return `${index === 0 ? 'M' : 'L'}${x.toFixed(1)},${y.toFixed(1)}`;
        })
        .join(' ');
}

/** Chemin SVG « aire » fermée sous la ligne (maquette : $areaPath). */
export function landingAreaPath(
    points: number[],
    width: number,
    height: number,
    options: { pad?: number } = {},
): string {
    const line = landingLinePath(points, width, height, options);

    if (line === '') {
        return '';
    }

    const pad = options.pad ?? 4;

    return `${line} L${(width - pad).toFixed(1)},${height} L${pad},${height} Z`;
}

/** Coordonnée en pourcentage dans le canvas de la démo (maquette : pct). */
export function landingPct(value: number, total: number): string {
    return ((value / total) * 100).toFixed(3) + '%';
}

/** Garde de la maquette : mouvement réduit demandé par l'utilisateur. */
export function prefersReducedMotion(): boolean {
    return (
        typeof window !== 'undefined' &&
        window.matchMedia('(prefers-reduced-motion: reduce)').matches
    );
}
