import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { useOtpCode } from '@/composables/useOtpCode';

/*
 * L'environnement de test est « node » : la classe DOM HTMLInputElement
 * n'existe pas. On l'installe en doublure pour que setDigitRef accepte
 * nos cases factices — chacune porte un espion focus, ce qui rend
 * l'auto-avance et le retour arrière observables sans navigateur.
 */
class FakeInput {
    focus = vi.fn();
}

/** Branche six cases factices sur le composable et les retourne. */
function attachInputs(otp: ReturnType<typeof useOtpCode>): FakeInput[] {
    const inputs = Array.from({ length: 6 }, () => new FakeInput());

    inputs.forEach((input, index) => otp.setDigitRef(index, input));

    return inputs;
}

/** Événement input avec une cible portant la valeur saisie. */
function digitInput(value: string): Event {
    return { target: { value } } as unknown as Event;
}

/** Événement paste avec un presse-papiers factice. */
function pasteEvent(text: string): ClipboardEvent {
    return {
        clipboardData: { getData: () => text },
        preventDefault: vi.fn(),
    } as unknown as ClipboardEvent;
}

describe('useOtpCode', () => {
    beforeEach(() => {
        vi.stubGlobal('HTMLInputElement', FakeInput);
    });

    afterEach(() => {
        vi.unstubAllGlobals();
    });

    it('expose six cases vides et un code vide au départ', () => {
        const otp = useOtpCode();

        expect(otp.digits.value).toEqual(['', '', '', '', '', '']);
        expect(otp.code.value).toBe('');
    });

    it('setCode filtre les non-chiffres, borne à six et complète à droite', () => {
        const otp = useOtpCode();

        otp.setCode('12a34B567890');
        expect(otp.digits.value).toEqual(['1', '2', '3', '4', '5', '6']);
        expect(otp.code.value).toBe('123456');

        otp.setCode('42');
        expect(otp.digits.value).toEqual(['4', '2', '', '', '', '']);
    });

    it('onDigitInput enregistre le chiffre, purge le champ et avance d’une case', () => {
        const otp = useOtpCode();
        const inputs = attachInputs(otp);
        const event = digitInput('7');

        otp.onDigitInput(1, event);

        expect(otp.digits.value[1]).toBe('7');
        expect((event.target as HTMLInputElement).value).toBe('7');
        expect(inputs[2].focus).toHaveBeenCalledOnce();
    });

    it('onDigitInput ignore les non-chiffres sans avancer', () => {
        const otp = useOtpCode();
        const inputs = attachInputs(otp);

        otp.onDigitInput(2, digitInput('x'));

        expect(otp.digits.value[2]).toBe('');
        expect(inputs[3].focus).not.toHaveBeenCalled();
    });

    it('la dernière case n’avance pas au-delà de la fin du code', () => {
        const otp = useOtpCode();
        const inputs = attachInputs(otp);

        otp.onDigitInput(5, digitInput('9'));

        expect(otp.digits.value[5]).toBe('9');
        for (const input of inputs) {
            expect(input.focus).not.toHaveBeenCalled();
        }
    });

    it('Backspace sur une case vide revient à la case précédente', () => {
        const otp = useOtpCode();
        const inputs = attachInputs(otp);

        otp.onDigitKeydown(3, { key: 'Backspace' } as unknown as KeyboardEvent);

        expect(inputs[2].focus).toHaveBeenCalledOnce();
    });

    it('Backspace sur une case remplie ou sur la première case ne déplace pas le focus', () => {
        const otp = useOtpCode();
        const inputs = attachInputs(otp);

        otp.setCode('123456');
        otp.onDigitKeydown(2, { key: 'Backspace' } as unknown as KeyboardEvent);
        otp.onDigitKeydown(0, { key: 'Backspace' } as unknown as KeyboardEvent);

        for (const input of inputs) {
            expect(input.focus).not.toHaveBeenCalled();
        }
    });

    it('onPaste remplit les cases depuis un presse-papiers filtré', () => {
        const otp = useOtpCode();
        const event = pasteEvent('a1b2c3d4e5f7');

        otp.onPaste(event);

        expect(otp.code.value).toBe('123457');
        expect(event.preventDefault).toHaveBeenCalledOnce();
    });

    it('onPaste borne une copie trop longue aux six chiffres', () => {
        const otp = useOtpCode();

        otp.onPaste(pasteEvent('0123456789'));

        expect(otp.code.value).toBe('012345');
    });

    it('onPaste sans aucun chiffre ne consomme pas l’événement', () => {
        const otp = useOtpCode();
        const event = pasteEvent('code');

        otp.onPaste(event);

        expect(event.preventDefault).not.toHaveBeenCalled();
        expect(otp.code.value).toBe('');
    });
});
