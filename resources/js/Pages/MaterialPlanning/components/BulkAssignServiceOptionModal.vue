<script setup>
import { ref, computed } from 'vue';
import FormModal from './FormModal.vue';
import ProgressButton from './ProgressButton.vue';

const props = defineProps({
    count:           { type: Number, required: true },
    itemSubGroups:   { type: Array, default: () => [] }, // unique subgroups across the selected items
    serviceOptions:  { type: Array, default: () => [] },
    suppliers:       { type: Array, default: () => [] },
    saving:          { type: Boolean, default: false },
    error:           { type: String, default: null },
});

const emit = defineEmits(['close', 'assign']);

const activeOptions = computed(() => props.serviceOptions.filter(o => o.classificationName !== 'Suspended'));

// Narrow to bundles whose Item subgroup matches one of the selected items' —
// but only when that leaves something to pick (mixed/no subgroups fall back
// to the full list rather than dead-ending the picker).
const options = computed(() => {
    if (!props.itemSubGroups.length) return activeOptions.value;
    const targets = new Set(props.itemSubGroups.map(s => s.trim().toLowerCase()));
    const matches = activeOptions.value.filter(o => targets.has((o.itemSubgroupLabel || '').trim().toLowerCase()));
    return matches.length ? matches : activeOptions.value;
});
const isFiltered = computed(() => props.itemSubGroups.length && options.value.length < activeOptions.value.length);
const selectedId = ref(null);

const subtitle = computed(() => {
    let s = `Pick which bundle fulfills the selected item${props.count === 1 ? '' : 's'} — every service in the bundle applies to each, replacing whatever they currently carry.`;
    if (isFiltered.value) s += ` Showing bundles in the ${props.itemSubGroups.join(', ')} subgroup${props.itemSubGroups.length === 1 ? '' : 's'}.`;
    return s;
});

function supplierOf(code) { return props.suppliers.find(s => s.code === code); }
function fmtMoney(n) { return '$' + Number(n || 0).toLocaleString('en-US'); }

function confirm() {
    if (!selectedId.value) return;
    emit('assign', selectedId.value);
}

function close() { emit('close'); }
</script>

<template>
    <FormModal
        max-width="520px"
        :title="`Assign a service bundle to ${count} item${count === 1 ? '' : 's'}`"
        :subtitle="subtitle"
        @close="close"
    >
        <template #eyebrow>
            <span class="mono">{{ count }} item{{ count === 1 ? '' : 's' }}</span><span>·</span><span>Assign service</span>
        </template>

        <div v-if="!options.length" class="asm-empty">
            <p>No service option bundles exist yet — create one from the Service Options page first.</p>
        </div>

        <div v-else class="asm-list">
            <label
                v-for="o in options" :key="o.dbId"
                class="asm-opt"
                :class="{ 'asm-opt-on': selectedId === o.dbId }"
            >
                <input type="radio" name="basm-option" :value="o.dbId" v-model="selectedId"/>
                <div class="asm-opt-body">
                    <div class="asm-opt-head">
                        <span class="asm-opt-name">{{ o.name }}</span>
                        <span v-if="o.isDefault" class="asm-opt-default">Default</span>
                    </div>
                    <div class="asm-opt-meta">
                        <span class="mono">{{ fmtMoney(o.cost) }} total</span>
                        <span>·</span>
                        <span>{{ o.lead }}d lead</span>
                    </div>
                    <div v-if="o.services?.length" class="asm-opt-svcs">
                        <span v-for="s in o.services" :key="s.id" class="asm-svc-chip">{{ s.name }} · {{ supplierOf(s.supplier)?.name ?? s.supplier }}</span>
                    </div>
                </div>
            </label>
        </div>

        <template #footer-left>
            <span v-if="error" class="asm-err">{{ error }}</span>
        </template>
        <template #footer-actions>
            <ProgressButton
                v-if="options.length"
                variant="primary"
                :disabled="!selectedId"
                :loading="saving"
                :text="`Assign to ${count} item${count === 1 ? '' : 's'}`"
                loading-text="Assigning…"
                @click="confirm"
            />
        </template>
    </FormModal>
</template>

<style scoped>
.mono { font-family: ui-monospace, 'SF Mono', Menlo, monospace; }

.asm-empty { text-align: center; padding: 44px 8px; color: #76706a; font-size: 13px; }
.asm-empty p { margin: 0; }

.asm-list { display: flex; flex-direction: column; gap: 8px; padding: 20px 0; }
.asm-opt {
    display: flex; align-items: flex-start; gap: 10px;
    border: 1px solid #e8e4db; border-radius: 8px;
    padding: 10px 12px; cursor: pointer; transition: background .12s, border-color .12s;
}
.asm-opt:hover { border-color: #a39d96; }
.asm-opt-on { border-color: #0f766e; background: rgba(15,118,110,.06); }
.asm-opt input[type="radio"] { margin-top: 3px; accent-color: #0f766e; flex-shrink: 0; }
.asm-opt-body { flex: 1; min-width: 0; }
.asm-opt-head { display: flex; align-items: center; gap: 8px; }
.asm-opt-name { font-size: 13.5px; font-weight: 600; color: #1a1614; }
.asm-opt-default {
    font-size: 10.5px; font-weight: 700; color: #0f766e;
    background: rgba(15,118,110,.12); padding: 1px 7px; border-radius: 20px;
}
.asm-opt-meta { display: flex; gap: 6px; flex-wrap: wrap; font-size: 12px; color: #76706a; margin-top: 3px; }
.asm-opt-svcs { display: flex; gap: 5px; flex-wrap: wrap; margin-top: 6px; }
.asm-svc-chip { font-size: 11px; color: #3d3833; background: #efece4; padding: 2px 7px; border-radius: 20px; }

.asm-err { font-size: 12.5px; color: #991b1b; }
</style>
