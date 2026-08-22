<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { usePage } from '@inertiajs/vue3';
import axios from 'axios';

const props = defineProps({
    request:        { type: Object, required: true }, // detailRequest — needs .id (numeric PK) + .code
    lines:          { type: Array, default: () => [] },
    domains:        { type: Array, default: () => [] },
    serviceOptions: { type: Array, default: () => [] },
    suppliers:      { type: Array, default: () => [] },
    people:         { type: Array, default: () => [] },
    showItemValues: { type: Boolean, default: false },
});

const emit = defineEmits(['close', 'raised']);

const CO_REASONS = ['Overlay revision', 'Service option change', 'Scope increase', 'Scope reduction', 'Budget correction', 'Client request'];

const page = usePage();
const currentPerson = computed(() => {
    const userId = page.props.auth?.user?.id;
    return props.people.find(p => p.id === userId) || { name: page.props.auth?.user?.name, role: 'Requester' };
});

function domainOf(code) { return props.domains.find(d => d.code === code); }
function supplierOf(code) { return props.suppliers.find(s => s.code === code); }
function optionsFor(sku) { return props.serviceOptions.filter(o => o.sku === sku); }
function optionOf(dbId) { return props.serviceOptions.find(o => o.dbId === dbId); }
function fmtMoney(n) { return '$' + Number(n || 0).toLocaleString('en-US'); }

const avatarColors = ['#7c2d12', '#0f766e', '#b45309', '#1d4ed8', '#6b21a8', '#155e75', '#854d0e'];
function initialsOf(name) {
    if (!name) return '—';
    const parts = name.trim().split(/\s+/);
    return ((parts[0]?.[0] || '') + (parts[1]?.[0] || '')).toUpperCase();
}
function avatarColor(name) {
    const key = initialsOf(name);
    const h = (key.charCodeAt(0) + (key.charCodeAt(1) || 0)) % avatarColors.length;
    return avatarColors[h];
}

// ── Form state ────────────────────────────────────────────────────────────
const title = ref('');
const reason = ref('Overlay revision');
const note = ref('');
const edits = ref({});

function baseOf(i) {
    const l = props.lines[i];
    return { qty: l.qty, drop: false, opt: l.serviceOptionId ?? '' };
}
function rowOf(i) { return edits.value[i] || baseOf(i); }
function setRow(i, patch) { edits.value = { ...edits.value, [i]: { ...rowOf(i), ...patch } }; }

function rateOf(i, r) {
    const l = props.lines[i];
    const o = r.opt ? optionOf(r.opt) : null;
    return o ? o.cost : l.rate;
}
function rowDelta(i) {
    const l = props.lines[i], r = rowOf(i);
    const newQty = r.drop ? 0 : r.qty;
    return newQty * rateOf(i, r) - l.qty * l.rate;
}

const changedIdx = computed(() => Object.keys(edits.value)
    .map(Number)
    .filter(i => {
        const r = edits.value[i], b = baseOf(i);
        return r.drop || r.qty !== b.qty || r.opt !== b.opt;
    }));

const delta    = computed(() => changedIdx.value.reduce((s, i) => s + rowDelta(i), 0));
const oldValue = computed(() => props.lines.reduce((s, l) => s + l.qty * l.rate, 0));
const newValue = computed(() => oldValue.value + delta.value);
const needsReapproval = computed(() => Math.abs(delta.value) > 5000 || newValue.value > 50000);

const affectedDomains = computed(() => [...new Set(changedIdx.value.map(i => props.lines[i].domain).filter(Boolean))]);
const affectedLeadNames = computed(() => affectedDomains.value
    .map(code => {
        const label = domainOf(code)?.label ?? code;
        const lead = props.people.find(p => p.role === `Category Lead — ${label}`);
        return lead?.name ?? `the ${label} lead`;
    }));

const valid = computed(() => title.value.trim() && changedIdx.value.length > 0);

// ── Submit ────────────────────────────────────────────────────────────────
const saving = ref(false);
const error = ref(null);

async function raise() {
    if (!valid.value || saving.value) return;
    saving.value = true;
    error.value = null;
    try {
        const { data: co } = await axios.post(route('mp.change-orders.store'), {
            request_id: props.request.id,
            context: title.value.trim(),
            reason: reason.value,
        });
        for (const i of changedIdx.value) {
            const l = props.lines[i];
            const r = rowOf(i);
            const dropped = !!r.drop;
            await axios.post(route('mp.change-order-lines.store', co.dbId), {
                request_line_id: l.id,
                sku: l.sku,
                qty_before: l.qty,
                qty_after: dropped ? 0 : r.qty,
                rate_before: l.rate,
                rate_after: dropped ? l.rate : rateOf(i, r),
                service_option_before_id: l.serviceOptionId ?? null,
                service_option_after_id: dropped ? null : (r.opt || null),
                why: note.value.trim() || null,
            });
        }
        emit('raised', co.id);
        close();
    } catch (e) {
        error.value = e.response?.status === 403
            ? "You don't have permission to change one or more of these lines' domains."
            : 'Could not raise this change order. Please try again.';
    } finally {
        saving.value = false;
    }
}

function close() { emit('close'); }
function onEsc(e) { if (e.key === 'Escape') close(); }
onMounted(()   => document.addEventListener('keydown', onEsc));
onUnmounted(() => document.removeEventListener('keydown', onEsc));
</script>

<template>
    <Teleport to="body">
        <div class="skum-scrim" @click.self="close">
            <div class="skum nco" role="dialog" aria-modal="true">
                <header class="skum-hd">
                    <div class="skum-hd-l">
                        <div class="skum-hd-tag"><span class="mono">{{ request.code }}</span><span>·</span><span>Change order</span></div>
                        <h2 class="skum-title">Raise change order</h2>
                        <p class="skum-sub">Adjust quantities, switch the service option, or drop lines against the approved baseline. The diff is sent back through the approval path.</p>
                    </div>
                    <button class="skum-x" @click="close" aria-label="Close">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18"/></svg>
                    </button>
                </header>

                <div class="skum-body">
                    <section class="skum-sec">
                        <div class="skum-sec-h"><span class="skum-sec-t">Change order details</span></div>
                        <div class="form-grid">
                            <div class="field" style="grid-column: span 2">
                                <label class="field-lbl">Title</label>
                                <input v-model="title" placeholder="e.g. Extend flooring for broadcaster pad"/>
                            </div>
                            <div class="field">
                                <label class="field-lbl">Reason code</label>
                                <div class="sel">
                                    <select v-model="reason">
                                        <option v-for="r in CO_REASONS" :key="r" :value="r">{{ r }}</option>
                                    </select>
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6"/></svg>
                                </div>
                            </div>
                            <div class="field">
                                <label class="field-lbl">Raised by</label>
                                <div class="nco-by">
                                    <span class="mp-avatar" :style="{ background: avatarColor(currentPerson.name) }">{{ initialsOf(currentPerson.name) }}</span>
                                    {{ currentPerson.name }}{{ currentPerson.role ? ' · ' + currentPerson.role : '' }}
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="skum-sec">
                        <div class="skum-sec-h">
                            <span class="skum-sec-t">Lines</span>
                            <span class="skum-sec-help">{{ changedIdx.length ? `${changedIdx.length} line${changedIdx.length > 1 ? 's' : ''} changed` : 'edit a quantity to build the diff' }}</span>
                        </div>
                        <div v-if="!lines.length" class="mp-empty">No line items on this request yet.</div>
                        <div v-else class="nco-tbl">
                            <div class="nco-hd nco-hd-opt" :class="{ 'nco-hd-noval': !showItemValues }">
                                <div>SKU</div><div>Item</div><div>Service option</div><div class="ta-r">Baseline</div><div class="ta-r">New qty</div><div v-if="showItemValues" class="ta-r">Δ value</div><div/>
                            </div>
                            <div v-for="(l, i) in lines" :key="l.id" class="nco-row nco-row-opt"
                                :class="[{ 'nco-row-drop': rowOf(i).drop, 'nco-row-chg': !rowOf(i).drop && rowDelta(i) !== 0 }, showItemValues ? '' : 'nco-row-noval']"
                            >
                                <div class="mono nco-sku">{{ l.sku }}</div>
                                <div class="nco-name">{{ l.name }}</div>
                                <div class="nco-opt">
                                    <div class="sel sel-sm">
                                        <select :value="rowOf(i).opt" :disabled="rowOf(i).drop" @change="setRow(i, { opt: $event.target.value ? +$event.target.value : '' })">
                                            <option value="">Not set — catalog rate</option>
                                            <option v-for="o in optionsFor(l.sku)" :key="o.dbId" :value="o.dbId" :disabled="supplierOf(o.supplier)?.classificationName === 'Suspended'">
                                                {{ o.name }} · {{ supplierOf(o.supplier)?.name }}{{ supplierOf(o.supplier)?.classificationName === 'Suspended' ? ' — suspended' : '' }}
                                            </option>
                                        </select>
                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6"/></svg>
                                    </div>
                                    <div v-if="showItemValues && rateOf(i, rowOf(i)) !== l.rate" class="items-var" :class="rateOf(i, rowOf(i)) > l.rate ? 'diff-pos' : 'diff-neg'">
                                        rate {{ fmtMoney(l.rate) }} → {{ fmtMoney(rateOf(i, rowOf(i))) }}
                                    </div>
                                </div>
                                <div class="ta-r mono nco-base">{{ l.qty }}</div>
                                <div class="ta-r">
                                    <input class="nco-qty mono" type="number" min="0" :disabled="rowOf(i).drop"
                                        :value="rowOf(i).drop ? 0 : rowOf(i).qty" @input="setRow(i, { qty: +$event.target.value })"/>
                                </div>
                                <div v-if="showItemValues" class="ta-r mono" :class="rowDelta(i) > 0 ? 'diff-pos' : rowDelta(i) < 0 ? 'diff-neg' : 'nco-zero'">
                                    {{ rowDelta(i) === 0 ? '—' : (rowDelta(i) > 0 ? '+' : '−') + fmtMoney(Math.abs(rowDelta(i))) }}
                                </div>
                                <div>
                                    <button type="button" class="nco-drop" @click="setRow(i, { drop: !rowOf(i).drop })">
                                        {{ rowOf(i).drop ? 'Restore' : 'Drop' }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="skum-sec">
                        <div class="skum-sec-h"><span class="skum-sec-t">Justification</span><span class="skum-sec-help">visible to every approver</span></div>
                        <textarea rows="3" v-model="note" placeholder="What changed and why — reference the layout or vendor document that drove it."></textarea>
                    </section>

                    <section v-if="showItemValues" class="skum-sec">
                        <div class="skum-sec-h"><span class="skum-sec-t">Impact</span></div>
                        <div class="nco-impact">
                            <div class="nco-imp"><span class="nco-imp-l">Approved baseline</span><span class="mono">{{ fmtMoney(oldValue) }}</span></div>
                            <span class="nco-imp-arrow">→</span>
                            <div class="nco-imp"><span class="nco-imp-l">After change</span><span class="mono">{{ fmtMoney(newValue) }}</span></div>
                            <div class="nco-imp"><span class="nco-imp-l">Net change</span><span class="mono" :class="delta > 0 ? 'diff-pos' : delta < 0 ? 'diff-neg' : ''">{{ delta === 0 ? '—' : (delta > 0 ? '+' : '−') + fmtMoney(Math.abs(delta)) }}</span></div>
                        </div>
                        <div class="nco-route" :class="{ 'nco-route-warn': needsReapproval }">
                            <span v-if="needsReapproval && affectedLeadNames.length">Re-approval required: <b>{{ affectedLeadNames.join(', ') }}</b> — net change exceeds the $5,000 delegated tolerance.</span>
                            <span v-else-if="needsReapproval">Re-approval required — net change exceeds the $5,000 delegated tolerance.</span>
                            <span v-else>Within tolerance — the affected category lead re-approves before the change applies.</span>
                        </div>
                    </section>
                    <section v-else class="skum-sec">
                        <div class="nco-route">Changed lines are sent back through the approval path for the affected category lead(s) to re-approve.</div>
                    </section>
                </div>

                <footer class="skum-ft">
                    <div class="skum-ft-l">
                        <span v-if="error" class="skum-ft-warn"><span class="skum-ft-dot" style="background:#b45309"></span>{{ error }}</span>
                        <span v-else-if="valid" class="skum-ft-ok"><span class="skum-ft-dot" style="background:#16a34a"></span>Ready to raise</span>
                        <span v-else class="skum-ft-warn"><span class="skum-ft-dot" style="background:#b45309"></span>Add a title and change a quantity or service option</span>
                    </div>
                    <div class="skum-ft-r">
                        <button class="mp-btn" @click="close">Cancel</button>
                        <button class="mp-btn mp-btn-primary" :disabled="!valid || saving" @click="raise">{{ saving ? 'Raising…' : 'Raise change order' }}</button>
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

.mp-empty { font-size: 13px; color: #76706a; padding: 20px; text-align: center; }
.mp-avatar {
    display: inline-flex; align-items: center; justify-content: center;
    width: 22px; height: 22px; border-radius: 50%;
    color: #fff; font-size: 9.5px; font-weight: 700; flex-shrink: 0;
}

.diff-pos { color: #166534; }
.diff-neg { color: #991b1b; }

.mono { font-family: ui-monospace, 'SF Mono', Menlo, monospace; }
.ta-r { text-align: right; }

/* ── Modal shell (shares skum shell used across Material Planning modals) ── */
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
.skum.nco { max-width: 900px; }
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
.skum-body > .skum-sec > textarea {
    width: 100%; border: 1px solid #e8e4db; border-radius: 7px; padding: 8px 11px;
    font-size: 13px; color: #1a1614; background: #fff; outline: none; resize: vertical; font-family: inherit; transition: border-color .12s;
}
.skum-body > .skum-sec > textarea:focus { border-color: #0f766e; box-shadow: 0 0 0 3px rgba(15,118,110,.1); }
.sel { position: relative; display: flex; align-items: center; }
.sel select {
    width: 100%; appearance: none; border: 1px solid #e8e4db; border-radius: 7px;
    padding: 8px 30px 8px 11px; font-size: 13px; color: #1a1614; background: #fff;
    outline: none; cursor: pointer; transition: border-color .12s;
}
.sel select:focus { border-color: #0f766e; box-shadow: 0 0 0 3px rgba(15,118,110,.1); }
.sel select:disabled { opacity: .5; cursor: not-allowed; }
.sel svg { position: absolute; right: 10px; pointer-events: none; color: #76706a; }
.sel-sm select { padding: 5px 22px 5px 7px; font-size: 11.5px; }
.sel-sm svg { right: 7px; }

.nco-by { display: flex; align-items: center; gap: 7px; font-size: 12.5px; color: #3d3833; height: 36px; }
.nco-tbl { border: 1px solid #e8e4db; border-radius: 8px; overflow: hidden; }
.nco-hd-opt, .nco-row-opt { display: grid; grid-template-columns: 100px 1fr 220px 66px 84px 88px 66px; gap: 10px; align-items: center; padding: 8px 12px; }
.nco-hd-opt.nco-hd-noval, .nco-row-opt.nco-row-noval { grid-template-columns: 100px 1fr 220px 66px 84px 66px; }
.nco-hd-opt {
    background: #fbfaf6; border-bottom: 1px solid #e8e4db;
    font-size: 10px; font-weight: 600; text-transform: uppercase; letter-spacing: .07em; color: #76706a;
}
.nco-row-opt { border-bottom: 1px solid #f3f0ea; font-size: 12.5px; }
.nco-row-opt:last-child { border-bottom: 0; }
.nco-sku { font-size: 11.5px; color: #3d3833; }
.nco-name { min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.nco-opt { min-width: 0; }
.nco-base { color: #76706a; }
.nco-zero { color: #76706a; }
.nco-row-chg { background: rgba(15,118,110,.05); }
.nco-row-drop { opacity: .55; }
.nco-row-drop .nco-name { text-decoration: line-through; }
.nco-qty { width: 100%; text-align: right; padding: 5px 7px; font-size: 12.5px; }
.nco-drop {
    background: none; border: 1px solid #e8e4db; border-radius: 5px; padding: 3px 8px;
    font-size: 11px; color: #3d3833; cursor: pointer; font-family: inherit;
}
.nco-drop:hover { border-color: #3d3833; color: #1a1614; }
.items-var { font-size: 10.5px; font-weight: 500; margin-top: 2px; white-space: nowrap; }

.nco-impact { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
.nco-imp {
    background: #fbfaf6; border: 1px solid #e8e4db; border-radius: 8px; padding: 9px 12px;
    display: flex; flex-direction: column; gap: 3px; font-size: 14px; font-weight: 600;
}
.nco-imp-l { font-size: 10px; text-transform: uppercase; letter-spacing: .07em; color: #76706a; font-weight: 600; }
.nco-imp-arrow { color: #76706a; }
.nco-route { margin-top: 12px; font-size: 12.5px; line-height: 1.5; color: #3d3833; border-left: 2px solid #efece4; padding-left: 11px; }
.nco-route-warn { border-left-color: #b45309; }

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
