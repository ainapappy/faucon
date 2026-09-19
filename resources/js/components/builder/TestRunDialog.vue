<script setup lang="ts">
import { Play } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { parseSampleInput } from '@/composables/useWorkflowTestRun';

/*
 * Modale « Tester le workflow » (maquette builder.html, U2) : l'input
 * d'échantillon est requis par le moteur, donc le test part d'ici — jamais
 * d'auto-run silencieux. La validation live reflète le contrat 422 du
 * backend (objet JSON strict) via `parseSampleInput`. Le bouton ne ferme pas
 * la modale lui-même : la page ferme à la réception de la réponse
 * (`onRunStarted`), si bien qu'une 422 laisse la modale ouverte pour corriger.
 */
const open = defineModel<boolean>('open', { required: true });

const props = defineProps<{
    /** Échantillon proposé au premier affichage (préremplissage du textarea). */
    sampleInput?: Record<string, unknown> | null;
    /** Requête en vol : le bouton est désactivé pour éviter un double POST. */
    launching?: boolean;
}>();

const emit = defineEmits<{
    launch: [sample: Record<string, unknown>];
}>();

const json = ref('');
const prefillDone = ref(false);

// Comme la maquette : le défaut est posé une seule fois, la saisie persiste
// entre les ouvertures (une 422 rouvre sur le texte à corriger).
watch(open, (isOpen) => {
    if (isOpen && !prefillDone.value) {
        prefillDone.value = true;
        json.value = props.sampleInput
            ? JSON.stringify(props.sampleInput, null, 2)
            : '';
    }
});

const validation = computed(() => parseSampleInput(json.value));
const launchable = computed(() => validation.value.sample !== null);

function launch(): void {
    if (!validation.value.sample) {
        return;
    }
    emit('launch', validation.value.sample);
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="max-w-[520px]" data-test="test-run-dialog">
            <DialogHeader>
                <DialogTitle>Tester le workflow</DialogTitle>
                <DialogDescription>
                    Lance un test réel du graphe avec l'input ci-dessous —
                    aucune action externe n'est déclenchée.
                </DialogDescription>
            </DialogHeader>

            <div class="grid gap-2">
                <Label for="test-run-input">Input d'échantillon (JSON)</Label>
                <Textarea
                    id="test-run-input"
                    v-model="json"
                    class="font-mono text-[12.5px] leading-relaxed"
                    rows="8"
                    spellcheck="false"
                    placeholder='{ "email": "client@example.com" }'
                    aria-label="Input d'échantillon JSON"
                    data-test="test-run-input"
                />
                <p
                    v-if="validation.error"
                    class="text-destructive text-xs"
                    data-test="test-run-error"
                >
                    {{ validation.error }}
                </p>
                <p v-else class="text-muted-foreground text-xs">
                    Disponible dans le contexte via
                    <code class="font-mono" v-pre>{{ trigger.* }}</code> — ex.
                    <code class="font-mono" v-pre>{{ trigger.email }}</code
                    >.
                </p>
            </div>

            <DialogFooter class="gap-2">
                <DialogClose as-child>
                    <Button
                        variant="ghost"
                        type="button"
                        data-test="test-run-cancel"
                    >
                        Annuler
                    </Button>
                </DialogClose>
                <Button
                    type="button"
                    :disabled="!launchable || launching"
                    data-test="test-run-launch"
                    @click="launch"
                >
                    <Spinner v-if="launching" class="h-3.5 w-3.5" />
                    <Play v-else class="h-[15px] w-[15px]" />
                    Lancer le test
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
