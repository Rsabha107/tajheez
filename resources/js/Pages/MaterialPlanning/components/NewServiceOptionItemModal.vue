<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import axios from 'axios';

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
            supplier: props.editItem.supplierCode,
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
    return { name: '', supplier: props.suppliers[0]?.code ?? '', cost: '', lead: '14', sla: '', capacity: '', contract: '', spec: '', itemGroupId: null, itemSubgroupId: null };
}
const form = ref(freshForm());

// Subgroups are scoped to a group — switching group drops a subgroup that no longer belongs to it.
const subgroupsForGroup = computed(() => props.itemSubgroups.filter(s => s.group === form.value.itemGroupId));
function onGroupChange() {
    if (!subgroupsForGroup.value.some(s => s.id === form.value.itemSubgroupId)) form.value.itemSubgroupId = null;
}

const formValid = computed(() => form.value.name.trim() && (+form.value.cost || 0) > 0 && form.value.supplier);

function close() { emit('close'); }

const saving = ref(false);
const error = ref(null);

async function save() {
    if (!formValid.value || saving.value) return;
    saving.value = true;
    error.value = null;
    try {
        const payload = {
            name: form.value.name.trim(),
            supplier_code: form.value.supplier,
            cost: +form.value.cost || 0,
            lead_days: +form.value.lead || 0,
            sla: form.value.sla.trim(),
            capacity: +form.value.capacity || 0,
            contract_reference: form.value.contract.trim() || null,
            spec: form.value.spec.trim() || null,
            item_group_id: form.value.itemGroupId,
            item_subgroup_id: form.value.itemSubgroupId,
        };
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

function onEsc(e) { if (e.key === 'Escape') close(); }
onMounted(()   => document.addEventListener('keydown', onEsc));
onUnmounted(() => document.removeEventListener('keydown', onEsc));
</script>

<template>
    <Teleport to="body">
        <div class="skum-scrim" @click.self="close">
            <div class="skum nsoi" role="dialog" aria-modal="true">
                <header class="skum-hd">
                    <div class="skum-hd-l">
                        <div class="skum-hd-tag"><span class="mono">{{ event.code }}</span><span>·</span><span>Service options</span></div>
                        <h2 class="skum-title">{{ editItem ? 'Edit service option' : 'New service option' }}</h2>
                        <p class="skum-sub">A single reusable service (e.g. "TV" or "Ooredoo Wi-Fi") that one or many bundles can include.</p>
                    </div>
                    <button class="skum-x" @click="close" aria-label="Close">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18"/></svg>
                    </button>
                </header>

                <div class="skum-body">
                    <section class="skum-sec">
                        <div class="skum-sec-h"><span class="skum-sec-t">Details</span></div>
                        <div class="form-grid">
                            <div class="field" style="grid-column: span 2">
                                <label class="field-lbl">Name</label>
                                <input v-model="form.name" placeholder="e.g. Vodafone Managed Wi-Fi"/>
                            </div>
                            <div class="field">
                                <label class="field-lbl">Supplier</label>
                                <div class="sel">
                                    <select v-model="form.supplier">
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
                                <label class="field-lbl">Spec</label>
                                <input v-model="form.spec" placeholder="optional"/>
                            </div>
                        </div>
                    </section>
                </div>

                <footer class="skum-ft">
                    <div class="skum-ft-l">
                        <span v-if="error" class="skum-ft-warn"><span class="skum-ft-dot" style="background:#b45309"></span>{{ error }}</span>
                        <span v-else-if="formValid" class="skum-ft-ok"><span class="skum-ft-dot" style="background:#16a34a"></span>Ready to {{ editItem ? 'save' : 'add' }}</span>
                        <span v-else class="skum-ft-warn"><span class="skum-ft-dot" style="background:#b45309"></span>Name and unit cost are required</span>
                    </div>
                    <div class="skum-ft-r">
                        <button class="mp-btn" @click="close">Cancel</button>
                        <button class="mp-btn mp-btn-primary" :disabled="!formValid || saving" @click="save">{{ saving ? (editItem ? 'Saving…' : 'Adding…') : (editItem ? 'Save changes' : 'Add service option') }}</button>
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

/* ── Modal shell (shared across MP create modals) ─────────────────────────── */
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
    width: 100%; max-width: 560px;
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
.skum-sec { padding: 20px 0; }
.skum-sec-h { display: flex; align-items: center; justify-content: space-between; gap: 10px; margin-bottom: 14px; }
.skum-sec-t { font-size: 13px; font-weight: 600; color: #1a1614; }

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
