<script setup>
import { ref, computed } from 'vue';
import axios from 'axios';
import FormModal from './FormModal.vue';
import ProgressButton from './ProgressButton.vue';

const props = defineProps({
    serviceOptionItems: { type: Array, default: () => [] },
    classifications:    { type: Array, default: () => [] },
    itemGroups:    { type: Array, default: () => [] },
    itemSubgroups: { type: Array, default: () => [] },
    event:               { type: Object, default: () => ({ code: 'EVT' }) },
    // When editing an existing bundle, pass it in — the modal pre-fills and PUTs instead of POSTs.
    editOption:          { type: Object, default: null },
});

const emit = defineEmits(['close', 'add']);

function fmtMoney(n) { return '$' + Number(n || 0).toLocaleString('en-US'); }

function defaultClassificationId() { return props.classifications.find(c => c.name === 'Normal')?.id ?? props.classifications[0]?.id ?? null; }

function freshForm() {
    if (props.editOption) {
        return {
            name: props.editOption.name,
            classificationId: props.editOption.classificationId ?? defaultClassificationId(),
            isDefault: props.editOption.isDefault,
            // A bundle's embedded `services` rows carry the item's real numeric id.
            itemIds: (props.editOption.services || []).map(s => s.id),
            itemGroupId: props.editOption.itemGroupId ?? null,
            itemSubgroupId: props.editOption.itemSubgroupId ?? null,
        };
    }
    return {
        name: '',
        classificationId: defaultClassificationId(),
        isDefault: false,
        itemIds: [],
        itemGroupId: null,
        itemSubgroupId: null,
    };
}
const form = ref(freshForm());

// Subgroups are scoped to a group — switching group drops a subgroup that no longer belongs to it.
const subgroupsForGroup = computed(() => props.itemSubgroups.filter(s => s.group === form.value.itemGroupId));
function onGroupChange() {
    if (!subgroupsForGroup.value.some(s => s.id === form.value.itemSubgroupId)) form.value.itemSubgroupId = null;
}

const itemsById = computed(() => new Map(props.serviceOptionItems.map(i => [i.dbId, i])));
const selectedItems = computed(() => form.value.itemIds.map(id => itemsById.value.get(id)).filter(Boolean));

const q = ref('');
const pickable = computed(() => {
    const k = q.value.trim().toLowerCase();
    let list = props.serviceOptionItems;
    if (k) {
        list = list.filter(i =>
            i.name.toLowerCase().includes(k) ||
            i.id.toLowerCase().includes(k) ||
            (i.supplierName || '').toLowerCase().includes(k)
        );
    }
    return list;
});

function isSelected(id) { return form.value.itemIds.includes(id); }
function toggleItem(id) {
    const idx = form.value.itemIds.indexOf(id);
    if (idx === -1) form.value.itemIds.push(id);
    else form.value.itemIds.splice(idx, 1);
}
function removeItem(id) {
    const idx = form.value.itemIds.indexOf(id);
    if (idx !== -1) form.value.itemIds.splice(idx, 1);
}

const bundleCost = computed(() => selectedItems.value.reduce((sum, i) => sum + (+i.cost || 0), 0));
const bundleLead = computed(() => selectedItems.value.reduce((max, i) => Math.max(max, +i.lead || 0), 0));
const formValid = computed(() =>
    form.value.name.trim() && form.value.itemIds.length > 0 &&
    !!form.value.itemGroupId && !!form.value.itemSubgroupId
);
// Only surfaced once the user tries to save with something missing, so the
// modal doesn't open already covered in red.
const attemptedSave = ref(false);
const fieldErrors = computed(() => ({
    name: attemptedSave.value && !form.value.name.trim(),
    items: attemptedSave.value && form.value.itemIds.length === 0,
    itemGroupId: attemptedSave.value && !form.value.itemGroupId,
    itemSubgroupId: attemptedSave.value && !form.value.itemSubgroupId,
}));

function close() { emit('close'); }

const saving = ref(false);
const error = ref(null);

async function addOption() {
    if (saving.value) return;
    if (!formValid.value) { attemptedSave.value = true; return; }
    saving.value = true;
    error.value = null;
    try {
        const payload = {
            name: form.value.name.trim(),
            classification_id: form.value.classificationId,
            is_default: form.value.isDefault,
            service_option_item_ids: form.value.itemIds,
            item_group_id: form.value.itemGroupId,
            item_subgroup_id: form.value.itemSubgroupId,
        };
        if (!props.editOption) payload.event_id = props.event.id;
        const { data } = props.editOption
            ? await axios.put(route('mp.service-options.update', props.editOption.dbId), payload)
            : await axios.post(route('mp.service-options.store'), payload);
        emit('add', data);
    } catch (e) {
        error.value = e.response?.status === 403
            ? "You don't have permission to manage bundles."
            : 'Could not save this bundle. Please try again.';
    } finally {
        saving.value = false;
    }
}

</script>

<template>
    <FormModal
        :title="editOption ? 'Edit bundle' : 'New bundle'"
        subtitle="A bundle groups one or many service options that can be assigned to any request item together — assigning it applies every option below to that item."
        @close="close"
    >
        <template #eyebrow>
            <span class="mono">{{ event.code }}</span><span>·</span><span>Bundles</span>
        </template>

                    <section class="skum-sec">
                        <div class="skum-sec-h"><span class="skum-sec-t">Bundle</span></div>
                        <div class="form-grid">
                            <div class="field" style="grid-column: span 2">
                                <label class="field-lbl">Bundle name <span class="field-req">*</span></label>
                                <input v-model="form.name" placeholder="e.g. TV plus Ooredoo" :class="{ 'field-bad': fieldErrors.name }"/>
                                <span v-if="fieldErrors.name" class="field-err">Required</span>
                            </div>
                            <div class="field">
                                <label class="field-lbl">Classification</label>
                                <div class="sel">
                                    <select v-model="form.classificationId">
                                        <option v-for="c in classifications" :key="c.id" :value="c.id">{{ c.name }}</option>
                                    </select>
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6"/></svg>
                                </div>
                            </div>
                            <div class="field">
                                <label class="field-lbl">Item group <span class="field-req">*</span></label>
                                <div class="sel" :class="{ 'sel-bad': fieldErrors.itemGroupId }">
                                    <select v-model="form.itemGroupId" @change="onGroupChange">
                                        <option :value="null" disabled>Select a group…</option>
                                        <option v-for="g in itemGroups" :key="g.id" :value="g.id">{{ g.domainLabel ? g.domainLabel + ' · ' : '' }}{{ g.label }}</option>
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
                                <label class="nso-check"><input type="checkbox" v-model="form.isDefault"/> Make this the default option — new request lines pre-fill with it</label>
                            </div>
                        </div>
                    </section>

                    <section class="skum-sec">
                        <div class="skum-sec-h">
                            <span class="skum-sec-t">Service options in this bundle <span class="field-req">*</span></span>
                            <span class="skum-sec-help">{{ form.itemIds.length }} selected</span>
                        </div>
                        <span v-if="fieldErrors.items" class="field-err">Select at least one service option</span>

                        <div v-if="selectedItems.length" class="bnd-chips">
                            <span v-for="i in selectedItems" :key="i.dbId" class="bnd-chip">
                                {{ i.name }}
                                <button type="button" class="bnd-chip-x" @click="removeItem(i.dbId)" aria-label="Remove">
                                    <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18"/></svg>
                                </button>
                            </span>
                        </div>

                        <div class="bnd-search">
                            <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5" stroke-linecap="round"/></svg>
                            <input v-model="q" placeholder="Search service options by name, code or supplier…"/>
                        </div>

                        <div class="bnd-pick-list" :class="{ 'bnd-pick-list-bad': fieldErrors.items }">
                            <label v-for="i in pickable" :key="i.dbId" class="bnd-pick-row" :class="{ 'bnd-pick-row-on': isSelected(i.dbId) }">
                                <input type="checkbox" :checked="isSelected(i.dbId)" @change="toggleItem(i.dbId)"/>
                                <div class="bnd-pick-body">
                                    <div class="bnd-pick-name">{{ i.name }} <span class="mono bnd-pick-code">{{ i.id }}</span></div>
                                    <div class="bnd-pick-meta">{{ i.supplierName }} · {{ fmtMoney(i.cost) }} · {{ i.lead }}d lead</div>
                                </div>
                            </label>
                            <div v-if="!pickable.length" class="bnd-pick-empty">No service options match this search.</div>
                        </div>
                    </section>

                    <section class="skum-sec">
                        <div class="skum-sec-h"><span class="skum-sec-t">Bundle totals</span></div>
                        <div class="nco-impact">
                            <div class="nco-imp"><span class="nco-imp-l">Total cost</span><span class="mono">{{ fmtMoney(bundleCost) }}</span></div>
                            <div class="nco-imp"><span class="nco-imp-l">Bundle lead</span><span class="mono">{{ bundleLead }} d</span></div>
                        </div>
                        <div class="nco-route">
                            <span v-if="form.isDefault">Existing lines keep the option they already carry. Only new lines pre-fill with this one — switching a live line still needs a change order.</span>
                            <span v-else>Assigning this bundle to a request item applies every service option above to that item.</span>
                        </div>
                    </section>

        <template #footer-left>
            <span v-if="error" class="skum-ft-warn"><span class="skum-ft-dot" style="background:#b45309"></span>{{ error }}</span>
            <span v-else-if="formValid" class="skum-ft-ok"><span class="skum-ft-dot" style="background:#16a34a"></span>Ready to {{ editOption ? 'save' : 'add' }}</span>
            <span v-else class="skum-ft-warn"><span class="skum-ft-dot" style="background:#b45309"></span>Name, at least one service option, group &amp; subgroup are required</span>
        </template>
        <template #footer-actions>
            <ProgressButton
                variant="primary"
                :loading="saving"
                :text="editOption ? 'Save changes' : 'Add bundle'"
                :loading-text="editOption ? 'Saving…' : 'Adding…'"
                @click="addOption"
            />
        </template>
    </FormModal>
</template>

<style scoped>
.mono { font-family: ui-monospace, 'SF Mono', Menlo, monospace; }

.skum-sec { padding: 20px 0; border-bottom: 1px solid #efece4; }
.skum-sec:last-child { border-bottom: none; }
.skum-sec-h { display: flex; align-items: center; justify-content: space-between; gap: 10px; margin-bottom: 14px; }
.skum-sec-t    { font-size: 13px; font-weight: 600; color: #1a1614; }
.skum-sec-help { font-size: 12px; color: #76706a; }

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
.nso-check { display: flex; align-items: center; gap: 8px; font-size: 12.5px; color: #3d3833; cursor: pointer; }
.nso-check input { accent-color: #0f766e; }

/* ── Bundle item picker ────────────────────────────────────────────────────── */
.bnd-chips { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 12px; }
.bnd-chip {
    display: inline-flex; align-items: center; gap: 6px;
    font-size: 12px; font-weight: 500; color: #0f766e; background: #ccfbf1;
    padding: 4px 6px 4px 10px; border-radius: 20px;
}
.bnd-chip-x {
    width: 16px; height: 16px; border-radius: 50%; border: none; background: rgba(15,118,110,.15);
    color: #0f766e; display: inline-flex; align-items: center; justify-content: center; cursor: pointer;
}
.bnd-chip-x:hover { background: rgba(15,118,110,.28); }
.bnd-search {
    display: flex; align-items: center; gap: 8px;
    background: #fff; border: 1px solid #e8e4db; border-radius: 6px; padding: 7px 10px; color: #76706a;
    margin-bottom: 10px;
}
.bnd-search input { border: 0; outline: none; background: transparent; flex: 1; font-size: 12.5px; color: #1a1614; }
.bnd-pick-list { display: flex; flex-direction: column; gap: 6px; max-height: 260px; overflow-y: auto; }
.bnd-pick-list-bad { border: 1px solid #dc2626; border-radius: 8px; padding: 6px; }
.bnd-pick-row {
    display: flex; align-items: center; gap: 10px;
    border: 1px solid #e8e4db; border-radius: 7px; padding: 9px 11px; cursor: pointer; transition: background .12s, border-color .12s;
}
.bnd-pick-row:hover { background: #fbfaf6; }
.bnd-pick-row-on { border-color: #0f766e; background: #f0fdfa; }
.bnd-pick-row input[type="checkbox"] { accent-color: #0f766e; flex-shrink: 0; }
.bnd-pick-body { min-width: 0; }
.bnd-pick-name { font-size: 12.5px; font-weight: 500; color: #1a1614; display: flex; align-items: center; gap: 8px; }
.bnd-pick-code { font-size: 10.5px; color: #76706a; font-weight: 400; }
.bnd-pick-meta { font-size: 11px; color: #76706a; margin-top: 2px; }
.bnd-pick-empty { text-align: center; padding: 16px; color: #76706a; font-size: 12.5px; }

.nco-impact { display: flex; align-items: center; gap: 14px; }
.nco-imp { display: flex; flex-direction: column; gap: 3px; }
.nco-imp-l { font-size: 11px; color: #76706a; text-transform: uppercase; letter-spacing: .05em; }
.nco-route { margin-top: 12px; font-size: 12px; color: #76706a; line-height: 1.5; background: #fbfaf6; border-radius: 6px; padding: 8px 10px; }

.skum-ft-ok, .skum-ft-warn { display: flex; align-items: center; gap: 5px; font-size: 12px; }
.skum-ft-ok   { color: #16a34a; }
.skum-ft-warn { color: #b45309; }
.skum-ft-dot  { width: 6px; height: 6px; border-radius: 50%; flex-shrink: 0; }
</style>
