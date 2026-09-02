<script setup>
import { ref, computed } from 'vue';
import FormModal from './FormModal.vue';
import ProgressButton from './ProgressButton.vue';

const props = defineProps({
    sku:             { type: String, required: true },
    itemName:        { type: String, default: '' },
    itemSubGroup:    { type: String, default: null },
    serviceOptions:  { type: Array, default: () => [] },
    suppliers:       { type: Array, default: () => [] },
    currentOptionId: { type: [Number, String], default: null },
    saving:          { type: Boolean, default: false },
    error:           { type: String, default: null },
});

const emit = defineEmits(['close', 'assign', 'create-new']);

// Bundles are no longer scoped to a SKU — any active bundle can be assigned to any request item.
const activeOptions = computed(() => props.serviceOptions.filter(o => o.classificationName !== 'Suspended'));

// Narrow to bundles whose Item subgroup matches this item's — but only when
// that actually leaves something to pick, so an item with no subgroup (or no
// matching bundle yet) doesn't end up with an empty, unassignable list.
const options = computed(() => {
    if (!props.itemSubGroup) return activeOptions.value;
    const target = props.itemSubGroup.trim().toLowerCase();
    const matches = activeOptions.value.filter(o => (o.itemSubgroupLabel || '').trim().toLowerCase() === target);
    return matches.length ? matches : activeOptions.value;
});
const isFiltered = computed(() => props.itemSubGroup && options.value.length < activeOptions.value.length);
const selectedId = ref(props.currentOptionId ?? null);

const subtitle = computed(() => {
    let s = `Pick which bundle fulfills ${props.itemName || props.sku} on this request — every service in the bundle applies to this line.`;
    if (isFiltered.value) s += ` Showing bundles in the ${props.itemSubGroup} subgroup.`;
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
        title="Assign a service bundle to this line"
        :subtitle="subtitle"
        @close="close"
    >
        <template #eyebrow>
            <span class="mono">{{ sku }}</span><span>·</span><span>Assign service</span>
        </template>

        <div v-if="!options.length" class="asm-empty">
            <p>No service option bundles exist yet.</p>
            <button class="mp-btn" @click="emit('create-new')">+ Create a new service option</button>
        </div>

        <div v-else class="asm-list">
            <label
                v-for="o in options" :key="o.dbId"
                class="asm-opt"
                :class="{ 'asm-opt-on': selectedId === o.dbId }"
            >
                <input type="radio" name="asm-option" :value="o.dbId" v-model="selectedId"/>
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
            <button v-else-if="options.length" type="button" class="asm-addnew" @click="emit('create-new')">+ New option instead</button>
        </template>
        <template #footer-actions>
            <ProgressButton
                v-if="options.length"
                variant="primary"
                :disabled="!selectedId"
                :loading="saving"
                text="Assign to line"
                loading-text="Assigning…"
                @click="confirm"
            />
        </template>
    </FormModal>
</template>

<style scoped>
.mp-btn {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 7px 12px; border-radius: 6px;
    border: 1px solid #e8e4db; background: #fff;
    font-size: 12.5px; font-weight: 500; color: #1a1614; cursor: pointer; transition: background .12s, border-color .12s;
}
.mp-btn:hover { background: #fbfaf6; border-color: #3d3833; }
.mp-btn:disabled { opacity: .5; cursor: not-allowed; }

.mono { font-family: ui-monospace, 'SF Mono', Menlo, monospace; }

.asm-empty { text-align: center; padding: 44px 8px; color: #76706a; font-size: 13px; }
.asm-empty p { margin: 0 0 14px; }

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

.asm-addnew {
    background: none; border: none; padding: 0;
    font-size: 12.5px; color: #0f766e; font-weight: 600; cursor: pointer;
}
.asm-addnew:hover { text-decoration: underline; }
.asm-err { font-size: 12.5px; color: #991b1b; }
</style>
