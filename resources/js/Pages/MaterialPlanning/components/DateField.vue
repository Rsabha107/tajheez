<script setup>
import { computed } from 'vue';
import DatePicker from 'primevue/datepicker';

const props = defineProps({
    modelValue:  { type: String, default: '' },
    placeholder: { type: String, default: 'DD/MM/YYYY' },
    // PrimeVue's own format tokens (dd/mm/yy = day/month/4-digit-year).
    dateFormat:  { type: String, default: 'dd/mm/yy' },
    invalid:     { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue']);

// The rest of the app (and Laravel's `date` validation rule) works with a
// plain 'YYYY-MM-DD' string, not PrimeVue's native Date object — parse/format
// by hand (not via PrimeVue's own dateFormat-based string mode) so the value
// round-trips unambiguously regardless of display format.
function parseIsoDate(str) {
    if (!str) return null;
    const [y, m, d] = str.split('-').map(Number);
    if (!y || !m || !d) return null;
    return new Date(y, m - 1, d);
}
function toIsoDate(date) {
    if (!(date instanceof Date) || isNaN(date)) return '';
    const y = date.getFullYear();
    const m = String(date.getMonth() + 1).padStart(2, '0');
    const d = String(date.getDate()).padStart(2, '0');
    return `${y}-${m}-${d}`;
}

const localValue = computed({
    get: () => parseIsoDate(props.modelValue),
    set: (v) => emit('update:modelValue', toIsoDate(v)),
});
</script>

<template>
    <DatePicker
        v-model="localValue"
        :date-format="dateFormat"
        :placeholder="placeholder"
        :invalid="invalid"
        show-icon
        icon-display="input"
        show-button-bar
        input-class="mp-date-input"
        class="mp-date-field"
    />
</template>

<style scoped>
.mp-date-field { width: 100%; display: block; }
:deep(.mp-date-input) {
    width: 100%; border: 1px solid #e8e4db; border-radius: 6px;
    padding: 8px 34px 8px 10px; font-size: 13px; background: #fff; color: #1a1614;
    outline: none; transition: border-color .15s; box-sizing: border-box;
}
:deep(.mp-date-input:focus) { border-color: #0f766e; box-shadow: 0 0 0 3px #0f766e1c; }
:deep(.mp-date-input.p-invalid) { border-color: #dc3545; }
:deep(.mp-date-input.p-invalid:focus) { box-shadow: 0 0 0 3px rgba(220,53,69,.15); }
:deep(.p-datepicker-input-icon-container) { color: #76706a; }
</style>
