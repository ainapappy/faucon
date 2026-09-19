import { computed, ref, type ComputedRef, type Ref } from 'vue';

/**
 * Logique du défi 2FA sous forme de cases OTP (maquette login, étape 2) :
 * auto-avance, retour arrière vers la case précédente, collage filtré
 * (seuls les chiffres sont conservés).
 */
export function useOtpCode(length = 6): {
    digits: Ref<string[]>;
    code: ComputedRef<string>;
    setDigitRef: (index: number, element: unknown) => void;
    focus: (index: number) => void;
    setCode: (value: string) => void;
    onDigitInput: (index: number, event: Event) => void;
    onDigitKeydown: (index: number, event: KeyboardEvent) => void;
    onPaste: (event: ClipboardEvent) => void;
} {
    const digits = ref<string[]>(Array.from({ length }, () => ''));
    const inputs = ref<Array<HTMLInputElement | null>>(
        Array.from({ length }, () => null),
    );

    const code = computed(() => digits.value.join(''));

    function setDigitRef(index: number, element: unknown): void {
        inputs.value[index] =
            element instanceof HTMLInputElement ? element : null;
    }

    function focus(index: number): void {
        inputs.value[index]?.focus();
    }

    function setCode(value: string): void {
        const filtered = value.replace(/\D/g, '').slice(0, length);

        digits.value = Array.from({ length }, (_, i) => filtered[i] ?? '');
    }

    function onDigitInput(index: number, event: Event): void {
        const target = event.target as HTMLInputElement;
        const value = target.value.replace(/\D/g, '').slice(-1);

        digits.value[index] = value;
        target.value = value;

        if (value && index < length - 1) {
            focus(index + 1);
        }
    }

    function onDigitKeydown(index: number, event: KeyboardEvent): void {
        if (event.key === 'Backspace' && !digits.value[index] && index > 0) {
            focus(index - 1);
        }
    }

    function onPaste(event: ClipboardEvent): void {
        const pasted = (event.clipboardData?.getData('text') ?? '')
            .replace(/\D/g, '')
            .slice(0, length);

        if (!pasted) {
            return;
        }

        event.preventDefault();
        setCode(pasted);
    }

    return {
        digits,
        code,
        setDigitRef,
        focus,
        setCode,
        onDigitInput,
        onDigitKeydown,
        onPaste,
    };
}
