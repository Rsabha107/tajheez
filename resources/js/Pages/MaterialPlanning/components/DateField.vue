<script setup>
import { computed } from 'vue';
import { DatePicker } from 'v-calendar';
import 'v-calendar/style.css';

const props = defineProps({
    modelValue:  { type: String, default: '' },
    placeholder: { type: String, default: 'DD/MM/YYYY' },
    inputMask:   { type: String, default: 'DD/MM/YYYY' },
    valueMask:   { type: String, default: 'YYYY-MM-DD' },
});

const emit = defineEmits(['update:modelValue']);

// v-model.string + masks.modelValue keeps the emitted value a plain date
// string (in `valueMask` format) rather than v-calendar's default JS Date —
// what backends validating with Laravel's `date` rule expect.
const masks = computed(() => ({ modelValue: props.valueMask, input: props.inputMask }));

const localValue = computed({
    get: () => props.modelValue,
    set: (v) => emit('update:modelValue', v),
});
</script>

<template>
    <DatePicker v-model.string="localValue" :masks="masks">
        <template #default="{ inputValue, inputEvents }">
            <input class="mp-date-input" :placeholder="placeholder" :value="inputValue" v-on="inputEvents"/>
        </template>
    </DatePicker>
</template>

<style scoped>
.mp-date-input {
    width: 100%; border: 1px solid #e8e4db; border-radius: 6px;
    padding: 8px 10px; font-size: 13px; background: #fff; color: #1a1614;
    outline: none; transition: border-color .15s; box-sizing: border-box;
}
.mp-date-input:focus { border-color: #0f766e; box-shadow: 0 0 0 3px #0f766e1c; }
</style>
