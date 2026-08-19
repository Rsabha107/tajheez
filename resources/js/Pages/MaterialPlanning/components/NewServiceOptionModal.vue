<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import axios from 'axios';

const props = defineProps({
    catalog:        Array,
    suppliers:      Array,
    domains:        Array,
    event:          { type: Object, default: () => ({ code: 'EVT' }) },
    lockedSku:      { type: String, default: null },
    // Optional — when provided, shows "N options today" for the selected item.
    serviceOptions: { type: Array, default: null },
});

const emit = defineEmits(['close', 'add']);

function domainOf(id)   { return props.domains.find(d => d.id === id) || props.domains[0]; }
function catalogOf(sku) { return props.catalog.find(c => c.sku === sku); }
function fmtMoney(n)    { return '$' + Number(n).toLocaleString('en-US'); }

function freshForm() {
    return {
        sku: props.lockedSku ?? (props.catalog[0]?.sku ?? ''),
        name: '', supplier: props.suppliers[0]?.id ?? '',
        cost: '', lead: '14', sla: 'Next business day',
        capacity: '', contract: '', spec: '', isDefault: false,
    };
}
const form = ref(freshForm());

const formItem = computed(() => catalogOf(form.value.sku));
const numCost  = computed(() => +form.value.cost || 0);
const existingForSku = computed(() => props.serviceOptions ? props.serviceOptions.filter(o => o.sku === form.value.sku).length : null);
const formValid = computed(() => form.value.name.trim() && numCost.value > 0);
const formVariance = computed(() => {
    if (!formItem.value) return null;
    const rate = formItem.value.rate;
    if (!numCost.value) return null;
    if (numCost.value === rate) return { flat: true, text: 'at catalog' };
    return { flat: false, pos: numCost.value > rate, text: (numCost.value > rate ? '+' : '−') + fmtMoney(Math.abs(numCost.value - rate)) };
});

function close() { emit('close'); }

const saving = ref(false);
const error = ref(null);

async function addOption() {
    if (!formValid.value || saving.value) return;
    saving.value = true;
    error.value = null;
    try {
        const { data } = await axios.post(route('mp.service-options.store'), {
            sku: form.value.sku,
            name: form.value.name.trim(),
            supplier_code: form.value.supplier,
            cost: numCost.value,
            lead_days: +form.value.lead || 0,
            sla: form.value.sla,
            capacity: +form.value.capacity || 0,
            contract_reference: form.value.contract.trim() || null,
            spec: form.value.spec.trim() || null,
            is_default: form.value.isDefault,
        });
        emit('add', data);
    } catch (e) {
        error.value = e.response?.status === 403
            ? "You don't have permission to add an option for this item's domain."
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
            <div class="skum nso" role="dialog" aria-modal="true">
                <header class="skum-hd">
                    <div class="skum-hd-l">
                        <div class="skum-hd-tag"><span class="mono">{{ event.code }}</span><span>·</span><span>Service options</span></div>
                        <h2 class="skum-title">New service option</h2>
                        <p class="skum-sub">One way of delivering a catalog item. Cost here overrides the catalog rate on any line that selects it.</p>
                    </div>
                    <button class="skum-x" @click="close" aria-label="Close">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18"/></svg>
                    </button>
                </header>

                <div class="skum-body">
                    <section class="skum-sec">
                        <div class="skum-sec-h">
                            <span class="skum-sec-t">Catalog item</span>
                            <span v-if="existingForSku !== null" class="skum-sec-help">{{ existingForSku }} option{{ existingForSku === 1 ? '' : 's' }} today</span>
                        </div>
                        <div class="form-grid">
                            <div class="field" style="grid-column: span 2" v-if="!lockedSku">
                                <label class="field-lbl">Item</label>
                                <div class="sel">
                                    <select v-model="form.sku">
                                        <option v-for="c in catalog" :key="c.sku" :value="c.sku">{{ c.sku }} · {{ c.name }}</option>
                                    </select>
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6"/></svg>
                                </div>
                            </div>
                            <div class="field" style="grid-column: span 2" v-else>
                                <label class="field-lbl">Item</label>
                                <div class="nso-static">{{ formItem ? `${formItem.sku} · ${formItem.name}` : lockedSku }}</div>
                            </div>
                            <div class="field">
                                <label class="field-lbl">Catalog rate</label>
                                <div class="nso-static mono">{{ formItem ? `${fmtMoney(formItem.rate)} / ${formItem.unit}` : '—' }}</div>
                            </div>
                            <div class="field">
                                <label class="field-lbl">Domain</label>
                                <div class="nso-static">
                                    <span v-if="formItem" class="mp-dtag" :style="{ background: domainOf(formItem.domain).chip, color: domainOf(formItem.domain).color }"><b>{{ formItem.domain }}</b></span>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="skum-sec">
                        <div class="skum-sec-h"><span class="skum-sec-t">Option</span></div>
                        <div class="form-grid">
                            <div class="field" style="grid-column: span 2">
                                <label class="field-lbl">Option name</label>
                                <input v-model="form.name" placeholder="e.g. Managed Wi-Fi — stadium tier"/>
                            </div>
                            <div class="field">
                                <label class="field-lbl">Supplier</label>
                                <div class="sel">
                                    <select v-model="form.supplier">
                                        <option v-for="s in suppliers" :key="s.id" :value="s.id">{{ s.name }}{{ s.status === 'suspended' ? ' (suspended)' : '' }}</option>
                                    </select>
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6"/></svg>
                                </div>
                            </div>
                            <div class="field">
                                <label class="field-lbl">Unit cost</label>
                                <input type="number" min="0" v-model="form.cost" :placeholder="formItem ? String(formItem.rate) : '0'"/>
                            </div>
                            <div class="field">
                                <label class="field-lbl">Lead time (days)</label>
                                <input type="number" min="0" v-model="form.lead"/>
                            </div>
                            <div class="field">
                                <label class="field-lbl">SLA</label>
                                <input v-model="form.sla" placeholder="e.g. 4h on-site, 24/7"/>
                            </div>
                            <div class="field">
                                <label class="field-lbl">Capacity per venue</label>
                                <input type="number" min="0" v-model="form.capacity" placeholder="optional"/>
                            </div>
                            <div class="field">
                                <label class="field-lbl">Contract / PO reference</label>
                                <input v-model="form.contract" placeholder="e.g. CTR-FWC26-VOD-014"/>
                            </div>
                            <div class="field" style="grid-column: span 2">
                                <label class="field-lbl">Technical specification</label>
                                <textarea rows="2" v-model="form.spec" placeholder="Capacity, model, what the supplier includes."></textarea>
                            </div>
                            <div class="field" style="grid-column: span 2">
                                <label class="nso-check"><input type="checkbox" v-model="form.isDefault"/> Make this the default option — new request lines pre-fill with it</label>
                            </div>
                        </div>
                    </section>

                    <section class="skum-sec">
                        <div class="skum-sec-h"><span class="skum-sec-t">Impact</span></div>
                        <div class="nco-impact">
                            <div class="nco-imp"><span class="nco-imp-l">Catalog rate</span><span class="mono">{{ formItem ? fmtMoney(formItem.rate) : '—' }}</span></div>
                            <span class="nco-imp-arrow">→</span>
                            <div class="nco-imp"><span class="nco-imp-l">Option cost</span><span class="mono">{{ numCost ? fmtMoney(numCost) : '—' }}</span></div>
                            <div class="nco-imp">
                                <span class="nco-imp-l">Variance</span>
                                <span v-if="!formVariance" class="mono">—</span>
                                <span v-else class="mono" :class="formVariance.flat ? '' : (formVariance.pos ? 'diff-pos' : 'diff-neg')">{{ formVariance.text }}</span>
                            </div>
                        </div>
                        <div class="nco-route">
                            <span v-if="form.isDefault">Existing lines keep the option they already carry. Only new lines pre-fill with this one — switching a live line still needs a change order.</span>
                            <span v-else>Selectable by domain leads after approval. Picking it moves the line value to the option cost.</span>
                        </div>
                    </section>
                </div>

                <footer class="skum-ft">
                    <div class="skum-ft-l">
                        <span v-if="error" class="skum-ft-warn"><span class="skum-ft-dot" style="background:#b45309"></span>{{ error }}</span>
                        <span v-else-if="formValid" class="skum-ft-ok"><span class="skum-ft-dot" style="background:#16a34a"></span>Ready to add</span>
                        <span v-else class="skum-ft-warn"><span class="skum-ft-dot" style="background:#b45309"></span>Name and unit cost are required</span>
                    </div>
                    <div class="skum-ft-r">
                        <button class="mp-btn" @click="close">Cancel</button>
                        <button class="mp-btn mp-btn-primary" :disabled="!formValid || saving" @click="addOption">{{ saving ? 'Adding…' : 'Add service option' }}</button>
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

.mp-dtag { display: inline-flex; align-items: center; gap: 4px; padding: 2px 8px; border-radius: 5px; font-size: 12px; font-weight: 600; }
.diff-pos { color: #166534; }
.diff-neg { color: #991b1b; }

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
    width: 100%; max-width: 640px;
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
.skum-body { padding: 0 24px; overflow-y: auto; max-height: 62vh; }
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
.field textarea { resize: vertical; min-height: 60px; font-family: inherit; }
.sel { position: relative; display: flex; align-items: center; }
.sel select {
    width: 100%; appearance: none; border: 1px solid #e8e4db; border-radius: 7px;
    padding: 8px 30px 8px 11px; font-size: 13px; color: #1a1614; background: #fff;
    outline: none; cursor: pointer; transition: border-color .12s;
}
.sel select:focus { border-color: #0f766e; box-shadow: 0 0 0 3px rgba(15,118,110,.1); }
.sel svg { position: absolute; right: 10px; pointer-events: none; color: #76706a; }
.nso-static {
    display: flex; align-items: center; min-height: 36px;
    border: 1px solid #e8e4db; border-radius: 7px; padding: 8px 11px;
    font-size: 13px; color: #1a1614; background: #fbfaf6;
}
.nso-check { display: flex; align-items: center; gap: 8px; font-size: 12.5px; color: #3d3833; cursor: pointer; }
.nso-check input { accent-color: #0f766e; }

.nco-impact { display: flex; align-items: center; gap: 14px; }
.nco-imp { display: flex; flex-direction: column; gap: 3px; }
.nco-imp-l { font-size: 11px; color: #76706a; text-transform: uppercase; letter-spacing: .05em; }
.nco-imp-arrow { color: #a39d96; font-size: 16px; }
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
