<script setup>
import { ref, computed } from 'vue';
import axios from 'axios';
import FormModal from './FormModal.vue';
import ProgressButton from './ProgressButton.vue';

const props = defineProps({
    suppliers: Array,
    itemGroups:    { type: Array, default: () => [] },
    itemSubgroups: { type: Array, default: () => [] },
    event:     { type: Object, default: () => ({ code: 'EVT' }) },
    // When editing an existing item, pass it in — the modal pre-fills and PUTs instead of POSTs.
    editItem:  { type: Object, default: null },
});

const emit = defineEmits(['close', 'add']);

function freshForm() {
    if (props.editItem) {
        return {
            name: props.editItem.name,
            supplier: props.editItem.supplierCode || '',
            cost: String(props.editItem.cost ?? ''),
            lead: String(props.editItem.lead ?? 0),
            sla: props.editItem.sla || '',
            capacity: props.editItem.capacity ? String(props.editItem.capacity) : '',
            contract: props.editItem.contract && props.editItem.contract !== '—' ? props.editItem.contract : '',
            spec: props.editItem.spec && props.editItem.spec !== '—' ? props.editItem.spec : '',
            itemGroupId: props.editItem.itemGroupId ?? null,
            itemSubgroupId: props.editItem.itemSubgroupId ?? null,
        };
    }
    return { name: '', supplier: '', cost: '', lead: '14', sla: '', capacity: '', contract: '', spec: '', itemGroupId: null, itemSubgroupId: null };
}
const form = ref(freshForm());

const eventItemGroups = computed(() => props.event?.id ? props.itemGroups.filter(g => g.eventId === props.event.id) : props.itemGroups);
const eventItemSubgroups = computed(() => props.event?.id ? props.itemSubgroups.filter(s => s.eventId === props.event.id) : props.itemSubgroups);

// Subgroups are scoped to a group — switching group drops a subgroup that no longer belongs to it.
const subgroupsForGroup = computed(() => eventItemSubgroups.value.filter(s => s.group === form.value.itemGroupId));
function onGroupChange() {
    if (!subgroupsForGroup.value.some(s => s.id === form.value.itemSubgroupId)) form.value.itemSubgroupId = null;
}

const formValid = computed(() =>
    form.value.name.trim() && (+form.value.cost || 0) > 0 &&
    !!form.value.itemGroupId && !!form.value.itemSubgroupId
);
// Only surfaced once the user tries to save with something missing, so the
// modal doesn't open already covered in red.
const attemptedSave = ref(false);
const fieldErrors = computed(() => ({
    name: attemptedSave.value && !form.value.name.trim(),
    itemGroupId: attemptedSave.value && !form.value.itemGroupId,
    itemSubgroupId: attemptedSave.value && !form.value.itemSubgroupId,
}));

function close() { emit('close'); }

const saving = ref(false);
const error = ref(null);

async function save() {
    if (saving.value) return;
    if (!formValid.value) { attemptedSave.value = true; return; }
    saving.value = true;
    error.value = null;
    try {
        const payload = {
            name: form.value.name.trim(),
            supplier_code: form.value.supplier || null,
            cost: +form.value.cost || 0,
            lead_days: +form.value.lead || 0,
            sla: form.value.sla.trim(),
            capacity: +form.value.capacity || 0,
            contract_reference: form.value.contract.trim() || null,
            spec: form.value.spec.trim() || null,
            item_group_id: form.value.itemGroupId,
            item_subgroup_id: form.value.itemSubgroupId,
        };
        if (!props.editItem) payload.event_id = props.event.id;
        const { data } = props.editItem
            ? await axios.put(route('mp.service-option-items.update', props.editItem.dbId), payload)
            : await axios.post(route('mp.service-option-items.store'), payload);
        emit('add', data);
    } catch (e) {
        error.value = e.response?.status === 403
            ? "You don't have permission to manage service options."
            : 'Could not save this service option. Please try again.';
    } finally {
        saving.value = false;
    }
}

</script>

<template>
    <FormModal
        max-width="560px"
        :title="editItem ? 'Edit service option' : 'New service option'"
        subtitle='A single reusable service (e.g. "TV" or "Ooredoo Wi-Fi") that one or many bundles can include.'
        @close="close"
    >
        <template #eyebrow>
            <span class="mono">{{ event.code }}</span><span>·</span><span>Service options</span>
        </template>

                    <section class="skum-sec">
                        <div class="skum-sec-h"><span class="skum-sec-t">Details</span></div>
                        <div class="form-grid">
                            <div class="field" style="grid-column: span 2">
                                <label class="field-lbl">Name <span class="field-req">*</span></label>
                                <input v-model="form.name" placeholder="e.g. Vodafone Managed Wi-Fi" :class="{ 'field-bad': fieldErrors.name }"/>
                                <span v-if="fieldErrors.name" class="field-err">Required</span>
                            </div>
                            <div class="field">
                                <label class="field-lbl">Supplier</label>
                                <div class="sel">
                                    <select v-model="form.supplier">
                                        <option value="">Select supplier…</option>
                                        <option v-for="sup in suppliers" :key="sup.id" :value="sup.code">{{ sup.name }}{{ sup.classificationName === 'Suspended' ? ' (suspended)' : '' }}</option>
                                    </select>
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6"/></svg>
                                </div>
                            </div>
                            <div class="field">
                                <label class="field-lbl">Unit cost</label>
                                <input type="number" min="0" v-model="form.cost" placeholder="0"/>
                            </div>
                            <div class="field">
                                <label class="field-lbl">Lead (days)</label>
                                <input type="number" min="0" v-model="form.lead"/>
                            </div>
                            <div class="field">
                                <label class="field-lbl">Capacity</label>
                                <input type="number" min="0" v-model="form.capacity" placeholder="optional"/>
                            </div>
                            <div class="field">
                                <label class="field-lbl">SLA</label>
                                <input v-model="form.sla" placeholder="e.g. 4h on-site"/>
                            </div>
                            <div class="field">
                                <label class="field-lbl">Contract / PO reference</label>
                                <input v-model="form.contract" placeholder="optional"/>
                            </div>
                            <div class="field">
                                <label class="field-lbl">Item group <span class="field-req">*</span></label>
                                <div class="sel" :class="{ 'sel-bad': fieldErrors.itemGroupId }">
                                    <select v-model="form.itemGroupId" @change="onGroupChange">
                                        <option :value="null" disabled>Select a group…</option>
                                        <option v-for="g in eventItemGroups" :key="g.id" :value="g.id">{{ g.domainLabel ? g.domainLabel + ' · ' : '' }}{{ g.label }}</option>
                                    </select>
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6"/></svg>
                                </div>
                                <span v-if="fieldErrors.itemGroupId" class="field-err">Required</span>
                            </div>
                            <div class="field">
                                <label class="field-lbl">Item subgroup <span class="field-req">*</span></label>
                                <div class="sel" :class="{ 'sel-bad': fieldErrors.itemSubgroupId }">
                                    <select v-model="form.itemSubgroupId" :disabled="!form.itemGroupId">
                                        <option :value="null" disabled>Select a subgroup…</option>
                                        <option v-for="s in subgroupsForGroup" :key="s.id" :value="s.id">{{ s.name }}</option>
                                    </select>
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6"/></svg>
                                </div>
                                <span v-if="fieldErrors.itemSubgroupId" class="field-err">Required</span>
                            </div>
                            <div class="field" style="grid-column: span 2">
                                <label class="field-lbl">Spec</label>
                                <input v-model="form.spec" placeholder="optional"/>
                            </div>
                        </div>
                    </section>

        <template #footer-left>
            <span v-if="error" class="skum-ft-warn"><span class="skum-ft-dot" style="background:#b45309"></span>{{ error }}</span>
            <span v-else-if="formValid" class="skum-ft-ok"><span class="skum-ft-dot" style="background:#16a34a"></span>Ready to {{ editItem ? 'save' : 'add' }}</span>
            <span v-else class="skum-ft-warn"><span class="skum-ft-dot" style="background:#b45309"></span>Name, unit cost, group &amp; subgroup are required</span>
        </template>
        <template #footer-actions>
            <ProgressButton
                variant="primary"
                :loading="saving"
                :text="editItem ? 'Save changes' : 'Add service option'"
                :loading-text="editItem ? 'Saving…' : 'Adding…'"
                @click="save"
            />
        </template>
    </FormModal>
</template>

<style scoped>
.mono { font-family: ui-monospace, 'SF Mono', Menlo, monospace; }

.skum-sec { padding: 20px 0; }
.skum-sec-h { display: flex; align-items: center; justify-content: space-between; gap: 10px; margin-bottom: 14px; }
.skum-sec-t { font-size: 13px; font-weight: 600; color: #1a1614; }

.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
.field { display: flex; flex-direction: column; gap: 5px; }
.field-lbl { font-size: 11.5px; font-weight: 600; color: #3d3833; }
.field-req { color: #b91c1c; font-weight: 700; }
.field-err { font-size: 11px; color: #b91c1c; font-weight: 500; }
.field input, .field textarea {
    border: 1px solid #e8e4db; border-radius: 7px; padding: 8px 11px;
    font-size: 13px; color: #1a1614; background: #fff; outline: none; transition: border-color .12s;
}
.field input:focus, .field textarea:focus { border-color: #0f766e; box-shadow: 0 0 0 3px rgba(15,118,110,.1); }
.field input.field-bad { border-color: #dc2626; }
.field input.field-bad:focus { box-shadow: 0 0 0 3px rgba(220,38,38,.1); }
.sel { position: relative; display: flex; align-items: center; }
.sel select {
    width: 100%; appearance: none; border: 1px solid #e8e4db; border-radius: 7px;
    padding: 8px 30px 8px 11px; font-size: 13px; color: #1a1614; background: #fff;
    outline: none; cursor: pointer; transition: border-color .12s;
}
.sel select:focus { border-color: #0f766e; box-shadow: 0 0 0 3px rgba(15,118,110,.1); }
.sel svg { position: absolute; right: 10px; pointer-events: none; color: #76706a; }
.sel-bad select { border-color: #dc2626; }
.sel-bad select:focus { box-shadow: 0 0 0 3px rgba(220,38,38,.12); }

.skum-ft-ok, .skum-ft-warn { display: flex; align-items: center; gap: 5px; font-size: 12px; }
.skum-ft-ok   { color: #16a34a; }
.skum-ft-warn { color: #b45309; }
.skum-ft-dot  { width: 6px; height: 6px; border-radius: 50%; flex-shrink: 0; }
</style>
