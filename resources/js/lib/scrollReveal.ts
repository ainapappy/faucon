import type { Directive } from 'vue';
import { prefersReducedMotion } from '@/lib/landing';

/*
 * Directive `v-reveal` de la page d'accueil (maquette welcome.html) :
 * apparition au scroll via IntersectionObserver, stagger éventuel par
 * la valeur du binding (v-reveal="140" → délai 140 ms). Les classes
 * sont retirées une fois la transition finie pour rendre à l'élément
 * ses transitions de survol.
 */

let revealObserver: IntersectionObserver | null = null;

function getRevealObserver(): IntersectionObserver {
    revealObserver ??= new IntersectionObserver(
        (entries) => {
            for (const entry of entries) {
                if (!entry.isIntersecting) {
                    continue;
                }

                const element = entry.target;

                element.classList.add('revealed');
                revealObserver?.unobserve(element);

                window.setTimeout(() => {
                    element.classList.remove('reveal', 'revealed');
                }, 900);
            }
        },
        { threshold: 0.12 },
    );

    return revealObserver;
}

/** Directive locale de la page Welcome : usage `v-reveal` ou `v-reveal="70"`. */
export const scrollReveal: Directive<HTMLElement, number | undefined> = {
    mounted(element, binding) {
        if (prefersReducedMotion() || !('IntersectionObserver' in window)) {
            return; // reste visible sans animation
        }

        element.classList.add('reveal');

        if (binding.value) {
            element.style.transitionDelay = `${binding.value}ms`;
        }

        getRevealObserver().observe(element);
    },
};
