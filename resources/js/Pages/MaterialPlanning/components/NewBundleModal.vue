<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import axios from 'axios';

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

function freshForm() {
    if (props.editOption) {
        return {
            name: props.editOption.name,
            classificationId: props.editOption.classificationId ?? props.classifications[0]?.id ?? null,
            isDefault: props.editOption.isDefault,
            // A bundle's embedded `services` rows carry the item's real numeric id.
            itemIds: (props.editOption.services || []).map(s => s.id),
            itemGroupId: props.editOption.itemGroupId ?? null,
            itemSubgroupId: props.editOption.itemSubgroupId ?? null,
        };
    }
    return {
        name: '',
        classificationId: props.classifications[0]?.id ?? null,
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
const formValid = computed(() => form.value.name.trim() && form.value.itemIds.length > 0);

function close() { emit('close'); }

const saving = ref(false);
const error = ref(null);

async function addOption() {
    if (!formValid.value || saving.value) return;
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

function onEsc(e) { if (e.key === 'Escape') close(); }
onMounted(()   => document.addEventListener('keydown', onEsc));
onUnmounted(() => document.removeEventListener('keydown', onEsc));
</script>

<template>
    <Teleport to="body">
        <div class="skum-scrim" @click.self="close">
            <div class="skum nso" role="dialog" aria-modal="true">
                <header class="skum-hd">
                    <div class="skum-hd-l">
                        <div class="skum-hd-tag"><span class="mono">{{ event.code }}</span><span>·</span><span>Bundles</span></div>
                        <h2 class="skum-title">{{ editOption ? 'Edit bundle' : 'New bundle' }}</h2>
                        <p class="skum-sub">A bundle groups one or many service options that can be assigned to any request item together — assigning it applies every option below to that item.</p>
                    </div>
                    <button class="skum-x" @click="close" aria-label="Close">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18"/></svg>
                    </button>
                </header>

                <div class="skum-body">
                    <section class="skum-sec">
                        <div class="skum-sec-h"><span class="skum-sec-t">Bundle</span></div>
                        <div class="form-grid">
                            <div class="field" style="grid-column: span 2">
                                <label class="field-lbl">Bundle name</label>
                                <input v-model="form.name" placeholder="e.g. TV plus Ooredoo"/>
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
                                <label class="field-lbl">Item group</label>
                                <div class="sel">
                                    <select v-model="form.itemGroupId" @change="onGroupChange">
                                        <option :value="null">No group</option>
                                        <option v-for="g in itemGroups" :key="g.id" :value="g.id">{{ g.domainLabel ? g.domainLabel + ' · ' : '' }}{{ g.label }}</option>
                                    </select>
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6"/></svg>
                                </div>
                            </div>
                            <div class="field">
                                <label class="field-lbl">Item subgroup</label>
                                <div class="sel">
                                    <select v-model="form.itemSubgroupId" :disabled="!form.itemGroupId">
                                        <option :value="null">No subgroup</option>
                                        <option v-for="s in subgroupsForGroup" :key="s.id" :value="s.id">{{ s.name }}</option>
                                    </select>
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6"/></svg>
                                </div>
                            </div>
                            <div class="field" style="grid-column: span 2">
                                <label class="nso-check"><input type="checkbox" v-model="form.isDefault"/> Make this the default option — new request lines pre-fill with it</label>
                            </div>
                        </div>
                    </section>

                    <section class="skum-sec">
                        <div class="skum-sec-h">
                            <span class="skum-sec-t">Service options in this bundle</span>
                            <span class="skum-sec-help">{{ form.itemIds.length }} selected</span>
                        </div>

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

                        <div class="bnd-pick-list">
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
                </div>

                <footer class="skum-ft">
                    <div class="skum-ft-l">
                        <span v-if="error" class="skum-ft-warn"><span class="skum-ft-dot" style="background:#b45309"></span>{{ error }}</span>
                        <span v-else-if="formValid" class="skum-ft-ok"><span class="skum-ft-dot" style="background:#16a34a"></span>Ready to {{ editOption ? 'save' : 'add' }}</span>
                        <span v-else class="skum-ft-warn"><span class="skum-ft-dot" style="background:#b45309"></span>Name and at least one service option are required</span>
                    </div>
                    <div class="skum-ft-r">
                        <button class="mp-btn" @click="close">Cancel</button>
                        <button class="mp-btn mp-btn-primary" :disabled="!formValid || saving" @click="addOption">{{ saving ? (editOption ? 'Saving…' : 'Adding…') : (editOption ? 'Save changes' : 'Add bundle') }}</button>
                    </div>
                </footer>
            </div>
        </div>
    </Teleport>
</template>

<style scoped>
.mp-btn {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 7px 12px; border-radius: 6px;
    border: 1px solid #e8e4db; background: #fff;
    font-size: 12.5px; font-weight: 500; color: #1a1614; cursor: pointer; transition: background .12s, border-color .12s;
}
.mp-btn:hover { background: #fbfaf6; border-color: #3d3833; }
.mp-btn:disabled { opacity: .4; cursor: not-allowed; }
.mp-btn-primary { background: #1a1614; border-color: #1a1614; color: #fff; }
.mp-btn-primary:hover { background: #0a0806; border-color: #0a0806; }

.mono { font-family: ui-monospace, 'SF Mono', Menlo, monospace; }

/* ── Modal shell (shares Add-SKU modal shell) ─────────────────────────────── */
@keyframes skum-fade { from { opacity: 0; } to { opacity: 1; } }
@keyframes skum-pop  { from { opacity: 0; transform: translateY(14px) scale(.97); } to { opacity: 1; transform: none; } }
.skum-scrim {
    position: fixed; inset: 0; z-index: 1000;
    background: rgba(26,22,20,.45);
    display: flex; align-items: flex-start; justify-content: center;
    padding: 40px 16px; overflow-y: auto;
    animation: skum-fade .18s ease;
}
.skum {
    background: #fff; border: 1px solid #e8e4db; border-radius: 14px;
    width: 100%; max-width: 680px;
    box-shadow: 0 20px 60px rgba(0,0,0,.18);
    animation: skum-pop .22s cubic-bezier(.34,1.3,.64,1);
    display: flex; flex-direction: column;
}
.skum-hd {
    display: flex; align-items: flex-start; justify-content: space-between;
    padding: 22px 24px 18px; border-bottom: 1px solid #e8e4db;
    background: #fbfaf6; border-radius: 13px 13px 0 0;
}
.skum-hd-tag {
    display: inline-flex; align-items: center; gap: 6px;
    font-size: 11px; color: #76706a; margin-bottom: 6px;
    background: #efece4; padding: 2px 8px; border-radius: 20px;
}
.skum-title { font-size: 17px; font-weight: 700; color: #1a1614; margin: 0 0 4px; }
.skum-sub   { font-size: 12.5px; color: #76706a; margin: 0; line-height: 1.5; }
.skum-x {
    width: 30px; height: 30px; border-radius: 7px;
    border: 1px solid #e8e4db; background: #fff;
    display: inline-flex; align-items: center; justify-content: center;
    cursor: pointer; color: #76706a; flex-shrink: 0; margin-left: 12px; transition: background .12s;
}
.skum-x:hover { background: #f6f5f1; }
.skum-body { padding: 0 24px; overflow-y: auto; max-height: 66vh; }
.skum-sec { padding: 20px 0; border-bottom: 1px solid #efece4; }
.skum-sec:last-child { border-bottom: none; }
.skum-sec-h { display: flex; align-items: center; justify-content: space-between; gap: 10px; margin-bottom: 14px; }
.skum-sec-t    { font-size: 13px; font-weight: 600; color: #1a1614; }
.skum-sec-help { font-size: 12px; color: #76706a; }

.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
.field { display: flex; flex-direction: column; gap: 5px; }
.field-lbl { font-size: 11.5px; font-weight: 600; color: #3d3833; }
.field input, .field textarea {
    border: 1px solid #e8e4db; border-radius: 7px; padding: 8px 11px;
    font-size: 13px; color: #1a1614; background: #fff; outline: none; transition: border-color .12s;
}
.field input:focus, .field textarea:focus { border-color: #0f766e; box-shadow: 0 0 0 3px rgba(15,118,110,.1); }
.sel { position: relative; display: flex; align-items: center; }
.sel select {
    width: 100%; appearance: none; border: 1px solid #e8e4db; border-radius: 7px;
    padding: 8px 30px 8px 11px; font-size: 13px; color: #1a1614; background: #fff;
    outline: none; cursor: pointer; transition: border-color .12s;
}
.sel select:focus { border-color: #0f766e; box-shadow: 0 0 0 3px rgba(15,118,110,.1); }
.sel svg { position: absolute; right: 10px; pointer-events: none; color: #76706a; }
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

.skum-ft {
    display: flex; align-items: center; justify-content: space-between;
    gap: 12px; padding: 16px 24px; border-top: 1px solid #e8e4db;
    background: #fbfaf6; border-radius: 0 0 13px 13px;
}
.skum-ft-l { display: flex; align-items: center; gap: 10px; min-width: 0; }
.skum-ft-r { display: flex; gap: 8px; flex-shrink: 0; }
.skum-ft-ok, .skum-ft-warn { display: flex; align-items: center; gap: 5px; font-size: 12px; }
.skum-ft-ok   { color: #16a34a; }
.skum-ft-warn { color: #b45309; }
.skum-ft-dot  { width: 6px; height: 6px; border-radius: 50%; flex-shrink: 0; }
</style>
