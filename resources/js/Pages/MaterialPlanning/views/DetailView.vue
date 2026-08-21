<script setup>
import { ref, computed, watch } from 'vue';
import axios from 'axios';
import NewServiceOptionModal from '../components/NewServiceOptionModal.vue';
import AssignServiceOptionModal from '../components/AssignServiceOptionModal.vue';

const props = defineProps({
    requests:      Array,
    detailId:      { type: String, default: null },
    detailData:    { type: Object, default: null },
    detailLoading: { type: Boolean, default: false },
    detailError:   { type: String, default: null },
    domains:       Array,
    venues:        Array,
    statuses:      Object,
    people:        Array,
    catalog:       { type: Array, default: () => [] },
    suppliers:     { type: Array, default: () => [] },
    serviceOptions: { type: Array, default: () => [] },
    permissions:   { type: Object, default: () => ({ isAdmin: false, managedDomain: null }) },
    showItemValues: { type: Boolean, default: false },
});

const emit = defineEmits(['go-to', 'refresh-detail']);

const stateStyles = {
    done:   { bg: '#dcfce7', fg: '#14532d', dot: '#166534', lbl: 'Approved' },
    now:    { bg: '#fef3c7', fg: '#92400e', dot: '#b45309', lbl: 'In review' },
    next:   { bg: '#f3f4f6', fg: '#6b7280', dot: '#9ca3af', lbl: 'Pending' },
    change: { bg: '#fde7d3', fg: '#7c2d12', dot: '#7c2d12', lbl: 'Change req' },
};

// ── Local state ───────────────────────────────────────────────────────────────
const detailTab = ref('overview');
watch(() => props.detailId, () => { detailTab.value = 'overview'; });

// ── Computed ──────────────────────────────────────────────────────────────────
// `detailData` is fetched fresh from the DB on every click (see Index.vue's
// openRequest); the `requests` list is only a page-load-time snapshot, so it's
// used solely as a placeholder while the real detail is still loading.
const detailRequest = computed(() =>
    props.detailData || props.requests.find(r => r.id === props.detailId) || props.requests[0] || {}
);

const detailLines      = computed(() => props.detailData?.lines ?? []);
const approvalWorkflow = computed(() => props.detailData?.approvalSteps ?? []);
const auditLog         = computed(() => props.detailData?.activity ?? []);
const changeOrders     = computed(() => props.detailData?.changeOrders ?? []);
const activeChangeOrder = computed(() => changeOrders.value[changeOrders.value.length - 1] ?? null);

const diffLines = computed(() => {
    const co = activeChangeOrder.value;
    if (!co) return [];
    return co.lines.map(l => ({
        sku: l.sku,
        name: l.name,
        qtyBefore: l.qtyBefore,
        qtyAfter: l.qtyAfter,
        dValue: Number(l.dValue) || 0,
        why: l.why,
        changed: l.qtyBefore !== l.qtyAfter,
    }));
});
const netChange = computed(() => activeChangeOrder.value?.delta ?? diffLines.value.reduce((sum, d) => sum + d.dValue, 0));

// ── Helpers ───────────────────────────────────────────────────────────────────
function domainOf(code) { return props.domains.find(d => d.code === code) || props.domains[0]; }
function venueOf(name)  { return props.venues.find(v => v.name === name || v.short_name === name) || props.venues[0]; }
function fmtMoney(n)    { return '$' + Number(n || 0).toLocaleString('en-US'); }
function catalogOf(sku) { return props.catalog.find(c => c.sku === sku); }

function fmtDate(s) {
    if (!s) return '—';
    return new Date(s.replace(' ', 'T')).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}
function fmtDateTime(s) {
    if (!s) return '—';
    return new Date(s.replace(' ', 'T')).toLocaleString('en-US', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
}

// ── Assign a service option to a line (domain managers + admins only) ────────
function canAddServiceOption(domain) {
    return props.permissions.isAdmin || props.permissions.managedDomain === domain;
}

const assignTarget = ref(null);   // the request line being assigned an option
const assignSaving = ref(false);
const assignError = ref(null);
const creatingNewOption = ref(false); // true = show NewServiceOptionModal for assignTarget instead

function openAssign(line) {
    assignTarget.value = line;
    assignError.value = null;
    creatingNewOption.value = false;
}
function closeAssign() {
    assignTarget.value = null;
    creatingNewOption.value = false;
}
function switchToCreateNew() {
    creatingNewOption.value = true;
}

async function assignOption(dbId) {
    if (!assignTarget.value) return;
    assignSaving.value = true;
    assignError.value = null;
    try {
        await axios.put(route('mp.request-lines.update', assignTarget.value.id), { service_option_id: dbId });
        closeAssign();
        emit('refresh-detail');
    } catch (e) {
        assignError.value = e.response?.status === 403
            ? "You don't have permission to assign an option for this item's domain."
            : 'Could not assign this service option.';
    } finally {
        assignSaving.value = false;
    }
}

// Option created via the "+ New option instead" fallback — assign it straight away.
function onOptionCreated(option) {
    creatingNewOption.value = false;
    if (option.dbId) assignOption(option.dbId);
}

const avatarColors = ['#7c2d12','#0f766e','#b45309','#1d4ed8','#6b21a8','#155e75','#854d0e'];
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
</script>

<template>
    <div class="mp-page mp-page-detail">
        <div class="mp-page-head">
            <div>
                <button class="mp-back-btn" @click="emit('go-to', 'requests')">← Back to requests</button>
                <h1 class="mp-page-title">
                    <span class="mono mp-detail-id">{{ detailRequest.code }}</span>
                </h1>
                <div class="mp-detail-meta">
                    <span class="mp-pill" :style="{ background: statuses[detailRequest.status]?.bg, color: statuses[detailRequest.status]?.fg }">
                        <span class="mp-pill-dot" :style="{ background: statuses[detailRequest.status]?.dot }"/>
                        {{ statuses[detailRequest.status]?.label }}
                    </span>
                    <span class="mp-dm-item"><span class="mp-dm-lbl">Venue</span> {{ venueOf(detailRequest.venue)?.name }}</span>
                    <span class="mp-dm-item"><span class="mp-dm-lbl">Site</span> {{ detailRequest.site }}</span>
                    <span v-if="showItemValues" class="mp-dm-item"><span class="mp-dm-lbl">Value</span> <b class="mono">{{ fmtMoney(detailRequest.value) }}</b></span>
                </div>
            </div>
            <div class="mp-head-actions">
                <button
                    v-if="detailRequest.status === 'draft'"
                    class="mp-btn"
                    @click="emit('go-to', 'new', { editCode: detailRequest.code })"
                ><i class="bx bx-pencil"></i> Edit</button>
                <button class="mp-btn">Duplicate</button>
                <button class="mp-btn">Open change order</button>
                <button class="mp-btn mp-btn-primary">✓ Approve</button>
            </div>
        </div>

        <div v-if="detailLoading" class="mp-card mp-detail-status">Loading request details…</div>
        <div v-else-if="detailError" class="mp-card mp-detail-status mp-detail-status-error">{{ detailError }}</div>

        <template v-else>
            <!-- Tabs -->
            <div class="mp-tabs">
                <button v-for="t in ['overview','items','change-order','audit']" :key="t"
                    class="mp-tab"
                    :class="{ 'mp-tab-on': detailTab === t }"
                    @click="detailTab = t"
                >
                    {{ t === 'overview' ? 'Overview' : t === 'items' ? `Items · ${detailLines.length}` : t === 'change-order' ? (activeChangeOrder ? `Change order · ${activeChangeOrder.code}` : 'Change order') : 'Audit log' }}
                </button>
            </div>

            <!-- Overview tab -->
            <div v-if="detailTab === 'overview'" class="mp-detail-grid">
                <div class="mp-card">
                    <div class="mp-card-head"><h3 class="mp-card-title">Approval workflow</h3></div>
                    <div v-if="!approvalWorkflow.length" class="mp-empty">No approval steps yet.</div>
                    <div v-else class="mp-apv-list">
                        <div v-for="step in approvalWorkflow" :key="step.id"
                            class="mp-apv"
                            :class="'mp-apv-' + step.state"
                        >
                            <div class="mp-apv-rail">
                                <span class="mp-apv-dot" :style="{ background: stateStyles[step.state]?.dot }"/>
                            </div>
                            <div class="mp-apv-body">
                                <div class="mp-apv-head">
                                    <span class="mp-avatar" :style="{ background: avatarColor(step.approverName) }">{{ initialsOf(step.approverName) }}</span>
                                    <div class="mp-apv-meta">
                                        <div class="mp-apv-name">{{ step.approverName || 'Unassigned' }}</div>
                                        <div class="mp-apv-role">{{ step.role }}</div>
                                    </div>
                                    <span class="mp-apv-pill" :style="{ background: stateStyles[step.state]?.bg, color: stateStyles[step.state]?.fg }">
                                        {{ stateStyles[step.state]?.lbl }}
                                    </span>
                                </div>
                                <div v-if="step.actedAt" class="mp-apv-when">{{ fmtDateTime(step.actedAt) }}</div>
                                <div v-if="step.note" class="mp-apv-note">{{ step.note }}</div>
                                <div v-if="step.state === 'now'" class="mp-apv-actions">
                                    <button class="mp-btn mp-btn-sm">Request change</button>
                                    <button class="mp-btn mp-btn-sm">Reject</button>
                                    <button class="mp-btn mp-btn-primary mp-btn-sm">Approve</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="mp-card">
                    <div class="mp-card-head"><h3 class="mp-card-title">Request summary</h3></div>
                    <table class="mp-rollup">
                        <tbody>
                            <tr><td class="mp-dm-lbl">ID</td><td class="mono">{{ detailRequest.code }}</td></tr>
                            <tr><td class="mp-dm-lbl">Submitted</td><td>{{ fmtDate(detailRequest.submittedAt) }}</td></tr>
                            <tr><td class="mp-dm-lbl">Priority</td><td>{{ detailRequest.priority }}</td></tr>
                            <tr><td class="mp-dm-lbl">Items</td><td class="mono">{{ detailRequest.items }}</td></tr>
                            <tr><td class="mp-dm-lbl">Total value</td><td class="mono">{{ fmtMoney(detailRequest.value) }}</td></tr>
                            <tr><td class="mp-dm-lbl">Owner</td><td>{{ detailRequest.owner || '—' }}</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Items tab -->
            <div v-if="detailTab === 'items'" class="mp-card mp-card-flush">
                <div v-if="!detailLines.length" class="mp-empty">No line items yet.</div>
                <table v-else class="mp-dt">
                    <thead><tr><th>SKU</th><th>Item</th><th>Domain</th><th class="ta-c">Qty</th><th>Unit</th><th v-if="showItemValues" class="ta-c">Rate</th><th v-if="showItemValues" class="ta-c">Total</th><th>Comment</th><th>Service</th><th></th></tr></thead>
                    <tbody>
                        <tr v-for="l in detailLines" :key="l.id">
                            <td class="mono">{{ l.sku }}</td>
                            <td>{{ l.name }}</td>
                            <td>
                                <span v-if="l.domain" class="mp-dtag" :style="{ background: domainOf(l.domain)?.chip, color: domainOf(l.domain)?.color }">
                                    <b>{{ l.domain }}</b>
                                </span>
                                <span v-else class="mp-muted">—</span>
                            </td>
                            <td class="ta-c mono">{{ l.qty }}</td>
                            <td class="mono">{{ l.unit }}</td>
                            <td v-if="showItemValues" class="ta-c mono">{{ fmtMoney(l.rate) }}</td>
                            <td v-if="showItemValues" class="ta-c mono">{{ fmtMoney(l.value) }}</td>
                            <td>{{ l.comment }}</td>
                            <td>
                                <div v-if="l.serviceOptionName" class="mp-dt-optn">
                                    <div>{{ l.serviceOptionName }}</div>
                                    <div class="mp-dt-optn-sup">{{ l.supplierName }}</div>
                                </div>
                                <span v-else class="mp-muted">Unassigned</span>
                            </td>
                            <td class="ta-r">
                                <button
                                    v-if="canAddServiceOption(catalogOf(l.sku)?.domain)"
                                    class="mp-btn mp-btn-sm"
                                    @click="openAssign(l)"
                                >{{ l.serviceOptionName ? 'Change' : '+ Option' }}</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <AssignServiceOptionModal
                v-if="assignTarget && !creatingNewOption"
                :sku="assignTarget.sku"
                :item-name="assignTarget.name"
                :service-options="serviceOptions"
                :suppliers="suppliers"
                :current-option-id="assignTarget.serviceOptionId"
                :saving="assignSaving"
                :error="assignError"
                @close="closeAssign"
                @assign="assignOption"
                @create-new="switchToCreateNew"
            />

            <NewServiceOptionModal
                v-if="assignTarget && creatingNewOption"
                :catalog="catalog"
                :suppliers="suppliers"
                :domains="domains"
                :locked-sku="assignTarget.sku"
                @close="closeAssign"
                @add="onOptionCreated"
            />

            <!-- Change order tab -->
            <div v-if="detailTab === 'change-order'" class="mp-card mp-card-flush">
                <div v-if="!activeChangeOrder" class="mp-empty">No change orders have been raised for this request.</div>
                <table v-else class="mp-diff">
                    <thead>
                        <tr>
                            <th>SKU</th><th>Item</th>
                            <th class="ta-c">Original qty</th><th class="ta-c">Proposed qty</th>
                            <th v-if="showItemValues" class="ta-c">Δ value</th><th>Why</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="d in diffLines" :key="d.sku" :class="{ 'mp-diff-changed': d.changed }">
                            <td class="mono">{{ d.sku }}</td>
                            <td>{{ d.name }}</td>
                            <td class="ta-c mono">{{ d.qtyBefore }}</td>
                            <td class="ta-c mono">{{ d.qtyAfter }}</td>
                            <td v-if="showItemValues" class="ta-c mono" :class="d.dValue > 0 ? 'mp-diff-pos' : d.dValue < 0 ? 'mp-diff-neg' : ''">
                                {{ d.dValue === 0 ? '—' : (d.dValue > 0 ? '+' : '') + fmtMoney(d.dValue) }}
                            </td>
                            <td class="mp-diff-reason">{{ d.why }}</td>
                        </tr>
                    </tbody>
                    <tfoot v-if="showItemValues">
                        <tr>
                            <td colspan="4" class="ta-r">Net change</td>
                            <td class="ta-c mono" :class="netChange > 0 ? 'mp-diff-pos' : netChange < 0 ? 'mp-diff-neg' : ''">
                                {{ (netChange > 0 ? '+' : '') + fmtMoney(netChange) }}
                            </td>
                            <td/>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <!-- Audit tab -->
            <div v-if="detailTab === 'audit'">
                <div v-if="!auditLog.length" class="mp-empty">No activity yet.</div>
                <ul v-else class="mp-audit">
                    <li v-for="e in auditLog" :key="e.id" class="mp-audit-row">
                        <div class="mp-audit-at mono">{{ fmtDateTime(e.createdAt) }}</div>
                        <div class="mp-audit-rail"><span/></div>
                        <div class="mp-audit-body">
                            <div class="mp-audit-line">
                                <span class="mp-avatar mp-avatar-sm" :style="{ background: avatarColor(e.actorName) }">{{ initialsOf(e.actorName) }}</span>
                                <span><b>{{ e.actorName || 'System' }}</b> {{ e.verb }} <span v-if="e.subject" class="mono mp-audit-what">{{ e.subject }}</span></span>
                            </div>
                            <div v-if="e.note" class="mp-audit-note">{{ e.note }}</div>
                        </div>
                    </li>
                </ul>
            </div>
        </template>
    </div>
</template>

<style scoped>
.mp-page { max-width: 100%; }
.mp-page-head {
    display: flex; justify-content: space-between; align-items: flex-start;
    margin-bottom: 20px;
    background: #fbfaf6; border: 1px solid #e8e4db;
    border-radius: 8px; padding: 14px 18px;
}
.mp-page-title { font-size: 20px; font-weight: 700; color: #1a1614; margin: 0; }
.mp-page-sub { font-size: 13px; color: #76706a; margin: 2px 0 0; }
.mp-head-actions { display: flex; gap: 8px; align-items: center; flex-shrink: 0; }

.mp-btn {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 7px 14px; border-radius: 7px;
    border: 1px solid #e8e4db; background: #fff;
    font-size: 13px; color: #1a1614; cursor: pointer;
    transition: background .15s;
}
.mp-btn:hover { background: #f6f5f1; }
.mp-btn-primary { background: #0f766e; border-color: #0f766e; color: #fff; }
.mp-btn-primary:hover { background: #0d9488; }
.mp-btn-sm { padding: 4px 10px; font-size: 12px; }

.mp-back-btn { background: none; border: none; color: #0f766e; cursor: pointer; font-size: 13px; margin-bottom: 6px; padding: 0; }
.mp-back-btn:hover { text-decoration: underline; }
.mp-detail-id { font-size: 20px; color: #1a1614; font-weight: 700; }
.mp-detail-meta { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; margin-top: 8px; }
.mp-dm-item { font-size: 12px; color: #76706a; }
.mp-dm-lbl { margin-right: 3px; }

.mp-detail-status { font-size: 13px; color: #76706a; text-align: center; padding: 30px 20px; }
.mp-detail-status-error { color: #991b1b; }
.mp-empty { font-size: 13px; color: #76706a; padding: 20px; text-align: center; }

.mp-tabs { display: flex; gap: 0; border-bottom: 2px solid #e8e4db; margin-bottom: 14px; }
.mp-tab {
    padding: 9px 16px; border: none; background: none; cursor: pointer;
    font-size: 13px; color: #76706a; border-bottom: 2px solid transparent;
    margin-bottom: -2px; transition: color .15s;
}
.mp-tab:hover { color: #1a1614; }
.mp-tab-on { color: #0f766e; border-bottom-color: #0f766e; font-weight: 600; }

.mp-detail-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }

.mp-card { background: #fff; border: 1px solid #e8e4db; border-radius: 10px; padding: 16px 20px; margin-bottom: 14px; }
.mp-card-flush { padding: 0; overflow: hidden; }
.mp-card-head { display: flex; align-items: baseline; gap: 10px; margin-bottom: 14px; }
.mp-card-title { font-size: 14px; font-weight: 600; color: #1a1614; margin: 0; }

.mp-dtag {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 2px 8px; border-radius: 5px;
    font-size: 12px; font-weight: 600;
}
.mp-pill {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 3px 9px; border-radius: 20px; font-size: 12px; font-weight: 600;
}
.mp-pill-dot { width: 6px; height: 6px; border-radius: 50%; }
.mp-avatar {
    display: inline-flex; align-items: center; justify-content: center;
    width: 28px; height: 28px; border-radius: 50%;
    color: #fff; font-size: 11px; font-weight: 700; flex-shrink: 0;
}
.mp-avatar-sm { width: 22px; height: 22px; font-size: 9px; }

.mp-apv-list { display: flex; flex-direction: column; gap: 0; }
.mp-apv { display: flex; gap: 12px; padding: 12px 0; border-bottom: 1px solid #f3f0ea; }
.mp-apv:last-child { border-bottom: none; }
.mp-apv-rail { width: 16px; display: flex; flex-direction: column; align-items: center; }
.mp-apv-dot { width: 10px; height: 10px; border-radius: 50%; margin-top: 6px; }
.mp-apv-body { flex: 1; }
.mp-apv-head { display: flex; align-items: center; gap: 8px; }
.mp-apv-meta { flex: 1; }
.mp-apv-name { font-size: 13px; font-weight: 600; color: #1a1614; }
.mp-apv-role { font-size: 11px; color: #76706a; }
.mp-apv-pill { font-size: 11px; font-weight: 600; padding: 2px 8px; border-radius: 20px; }
.mp-apv-when { font-size: 11px; color: #76706a; margin-top: 4px; font-family: monospace; }
.mp-apv-note { font-size: 12px; color: #545a6d; margin-top: 5px; padding: 6px 10px; background: #fbfaf6; border-radius: 6px; border-left: 3px solid #e8e4db; }
.mp-apv-actions { display: flex; gap: 6px; margin-top: 8px; }

.mp-rollup { width: 100%; border-collapse: collapse; font-size: 13px; }
.mp-rollup th { color: #76706a; font-size: 11px; text-transform: uppercase; letter-spacing: .05em; padding: 6px 8px; text-align: left; border-bottom: 1px solid #e8e4db; }
.mp-rollup td { padding: 10px 8px; border-bottom: 1px solid #f3f0ea; vertical-align: middle; color: #1a1614; }
.mp-rollup tr:last-child td { border-bottom: none; }

.mp-dt { width: 100%; border-collapse: collapse; font-size: 13px; }
.mp-dt th {
    background: #fbfaf6; border-bottom: 1px solid #e8e4db;
    color: #76706a; font-size: 11px; text-transform: uppercase; letter-spacing: .05em;
    padding: 10px 14px; text-align: left; white-space: nowrap;
}
.mp-dt th.ta-c { text-align: center; }
.mp-dt td { padding: 11px 14px; border-bottom: 1px solid #f3f0ea; vertical-align: middle; color: #1a1614; }
.mp-dt-optn { font-size: 12.5px; color: #1a1614; white-space: nowrap; }
.mp-dt-optn-sup { font-size: 11px; color: #76706a; margin-top: 1px; }
.mp-muted { color: #a39d96; font-size: 12px; }

.mp-diff { width: 100%; border-collapse: collapse; font-size: 13px; }
.mp-diff th { background: #fbfaf6; border-bottom: 1px solid #e8e4db; color: #76706a; font-size: 11px; text-transform: uppercase; letter-spacing: .05em; padding: 10px 14px; text-align: left; }
.mp-diff th.ta-c { text-align: center; }
.mp-diff td { padding: 10px 14px; border-bottom: 1px solid #f3f0ea; vertical-align: middle; }
.mp-diff-changed td { background: #fffbf0; }
.mp-diff-old { text-decoration: line-through; color: #76706a; font-size: 12px; }
.mp-diff-new { color: #1a1614; font-size: 12px; }
.mp-diff-old-inline { text-decoration: line-through; color: #76706a; }
.mp-diff-new-inline { color: #0f766e; font-weight: 600; }
.mp-diff-pos { color: #0f766e; }
.mp-diff-neg { color: #991b1b; }
.mp-diff-reason { font-size: 12px; color: #76706a; }
.mp-diff tfoot td { border-top: 2px solid #e8e4db; font-weight: 700; border-bottom: none; }

.mp-audit { list-style: none; margin: 0; padding: 0; }
.mp-audit-row { display: flex; gap: 12px; padding: 10px 0; }
.mp-audit-at { font-size: 11px; font-family: monospace; color: #76706a; width: 130px; flex-shrink: 0; padding-top: 2px; }
.mp-audit-rail { width: 16px; display: flex; flex-direction: column; align-items: center; }
.mp-audit-rail span { width: 2px; flex: 1; background: #e8e4db; border-radius: 1px; margin-top: 4px; }
.mp-audit-body { flex: 1; }
.mp-audit-line { display: flex; align-items: center; gap: 7px; font-size: 13px; color: #1a1614; }
.mp-audit-what { color: #0f766e; }
.mp-audit-note { font-size: 12px; color: #76706a; margin-top: 4px; padding: 5px 8px; background: #fbfaf6; border-radius: 6px; }

.mono { font-family: 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace; }
.ta-r { text-align: right; }
.ta-c { text-align: center; }
</style>
