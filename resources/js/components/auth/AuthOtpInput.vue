<script setup lang="ts">
import { onMounted, watch } from 'vue';
import { useOtpCode } from '@/composables/useOtpCode';

const props = withDefaults(
    defineProps<{
        modelValue?: string;
        name?: string;
        length?: number;
    }>(),
    { modelValue: '', name: 'code', length: 6 },
);

const emit = defineEmits<{
    'update:modelValue': [value: string];
}>();

const {
    digits,
    code,
    focus,
    setCode,
    setDigitRef,
    onDigitInput,
    onDigitKeydown,
    onPaste,
} = useOtpCode(props.length);

watch(code, (value) => {
    emit('update:modelValue', value);
});

watch(
    () => props.modelValue,
    (value) => {
        if (value !== code.value) {
            setCode(value);
        }
    },
    { immediate: true },
);

onMounted(() => {
    focus(0);
});
</script>

<template>
    <div class="otp-boxes">
        <input
            v-for="(_, index) in length"
            :key="index"
            :ref="(element) => setDigitRef(index, element)"
            :value="digits[index]"
            type="text"
            inputmode="numeric"
            maxlength="1"
            autocomplete="one-time-code"
            :aria-label="`Chiffre ${index + 1} du code`"
            :data-test="`otp-input-${index}`"
            @input="onDigitInput(index, $event)"
            @keydown.backspace="onDigitKeydown(index, $event)"
            @paste="onPaste($event)"
        />
        <input type="hidden" :name="name" :value="code" />
    </div>
</template>
