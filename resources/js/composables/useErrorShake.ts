import { ref, watch, type Ref } from 'vue';

type ErrorShakeSource = {
    processing: () => boolean;
    hasError: () => boolean;
    duration?: number;
};

/**
 * Animation « shake » de la maquette, jouée uniquement sur une erreur réelle :
 * déclenchée à chaque fin de soumission (processing true → false) alors qu'une
 * erreur est présente, elle rejoue donc à chaque échec consécutif.
 */
export function useErrorShake({
    processing,
    hasError,
    duration = 450,
}: ErrorShakeSource): Ref<boolean> {
    const shaking = ref(false);
    let resumeTimer: number | undefined;
    let stopTimer: number | undefined;

    watch([processing, hasError], ([isProcessing, error], [wasProcessing]) => {
        if (!wasProcessing || isProcessing || !error) {
            return;
        }

        window.clearTimeout(resumeTimer);
        window.clearTimeout(stopTimer);
        shaking.value = false;
        resumeTimer = window.setTimeout(() => {
            shaking.value = true;
        }, 20);
        stopTimer = window.setTimeout(() => {
            shaking.value = false;
        }, 20 + duration);
    });

    return shaking;
}
