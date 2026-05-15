<script setup>
import { ref, computed } from 'vue';

const props = defineProps({
    requests: Array,
    detailId: { type: String, default: null },
    domains:  Array,
    venues:   Array,
    statuses: Object,
    people:   Array,
});

const emit = defineEmits(['go-to']);

// ── Static data ───────────────────────────────────────────────────────────────
const detailLines = [
    { sku: 'OV-FL-0420', name: 'Modular Event Flooring 0.5×0.5 m', qty: 240, unit: 'm²', rate: 42,   comment: 'Per layout v3.1 — North end' },
    { sku: 'OV-FN-2200', name: 'Heras Fence Panel 3.5 m',          qty: 18,  unit: 'ea', rate: 36,   comment: 'Crowd separation' },
    { sku: 'LG-TN-1010', name: 'Standard Tent 10×10 m w/ Floor',   qty: 2,   unit: 'ea', rate: 5180, comment: 'Broadcaster waiting' },
    { sku: 'LG-FN-0312', name: 'Stacking Chair – Black Vinyl',      qty: 18,  unit: 'ea', rate: 28,   comment: 'Athlete bench' },
    { sku: 'IT-AV-0102', name: 'Samsung 75" QM75B Commercial',      qty: 4,   unit: 'ea', rate: 2950, comment: 'Run-of-show feed' },
    { sku: 'IT-AV-0411', name: 'Shure ULXD24/SM58 Wireless',        qty: 4,   unit: 'ea', rate: 1840, comment: 'Press scrum' },
];

const auditLog = [
    { at: 'Apr 23, 10:55', who: 'MC', verb: 'approved',         what: 'Category review',  note: 'After change order CO-001 accepted' },
    { at: 'Apr 23, 09:40', who: 'AR', verb: 'applied change',   what: 'CO-001',           note: 'Qty 240 → 286 m², swap mic SKU' },
    { at: 'Apr 23, 08:11', who: 'MC', verb: 'requested change', what: 'CO-001',           note: 'See diff: +46 m² flooring; +6 chairs' },
    { at: 'Apr 22, 14:28', who: 'JK', verb: 'approved',         what: 'L1 review',        note: null },
    { at: 'Apr 22, 11:02', who: 'SO', verb: 'commented',        what: '',                 note: 'Footprint confirmed with overlay v3.1' },
    { at: 'Apr 22, 09:14', who: 'AR', verb: 'submitted',        what: 'v1',               note: '14 lines · $184,320' },
    { at: 'Apr 22, 09:02', who: 'AR', verb: 'created draft',    what: '',                 note: null },
];

const approvalWorkflow = [
    { who: 'AR', role: 'You — Venue Planner',             state: 'done',   when: 'Apr 22, 09:14', note: 'Submitted with 14 line items.' },
    { who: 'JK', role: 'L1 — Operations Coordinator',    state: 'done',   when: 'Apr 22, 14:28', note: 'L1 review passed.' },
    { who: 'MC', role: 'Category Lead — Overlay',         state: 'change', when: 'Apr 23, 08:11', note: 'Increase flooring 240 → 286 m².' },
    { who: 'DV', role: 'Finance Controller (value > $50k)', state: 'now',  when: null,             note: null },
];
const stateStyles = {
    done:   { bg: '#dcfce7', fg: '#14532d', dot: '#166534', lbl: 'Approved' },
    now:    { bg: '#fef3c7', fg: '#92400e', dot: '#b45309', lbl: 'In review' },
    next:   { bg: '#f3f4f6', fg: '#6b7280', dot: '#9ca3af', lbl: 'Pending' },
    change: { bg: '#fde7d3', fg: '#7c2d12', dot: '#7c2d12', lbl: 'Change req' },
};

// ── Local state ───────────────────────────────────────────────────────────────
const detailTab = ref('overview');

// ── Computed ──────────────────────────────────────────────────────────────────
const detailRequest = computed(() =>
    props.requests.find(r => r.id === props.detailId) || props.requests[0]
);

const diffLines = computed(() => detailLines.map((b, i) => {
    const a = { ...b };
    if (i === 0) a.qty = 286;
    if (i === 3) a.qty = 24;
    if (i === 5) a.name = 'Shure QLXD24/SM58 Wireless (substituted)';
    const dValue = (a.qty - b.qty) * b.rate;
    return { ...b, after: a, dValue, changed: b.qty !== a.qty || b.name !== a.name };
}));

// ── Helpers ───────────────────────────────────────────────────────────────────
function domainOf(id)  { return props.domains.find(d => d.id === id) || props.domains[0]; }
function venueOf(code) { return props.venues.find(v => v.code === code) || props.venues[0]; }
function personOf(ini) { return props.people.find(p => p.initials === ini) || props.people[0]; }
function fmtMoney(n)   { return '$' + Number(n).toLocaleString('en-US'); }

const avatarColors = ['#7c2d12','#0f766e','#b45309','#1d4ed8','#6b21a8','#155e75','#854d0e'];
function avatarColor(initials) {
    const h = (initials.charCodeAt(0) + (initials.charCodeAt(1) || 0)) % avatarColors.length;
    return avatarColors[h];
}
</script>

<template>
    <div class="mp-page mp-page-detail">
        <div class="mp-page-head">
            <div>
                <button class="mp-back-btn" @click="emit('go-to', 'requests')">← Back to requests</button>
                <h1 class="mp-page-title">
                    {{ detailRequest.title }}
                    <span class="mono mp-detail-id">{{ detailRequest.id }}</span>
                </h1>
                <div class="mp-detail-meta">
                    <span class="mp-pill" :style="{ background: statuses[detailRequest.status]?.bg, color: statuses[detailRequest.status]?.fg }">
                        <span class="mp-pill-dot" :style="{ background: statuses[detailRequest.status]?.dot }"/>
                        {{ statuses[detailRequest.status]?.label }}
                    </span>
                    <span class="mp-dtag" :style="{ background: domainOf(detailRequest.domain).chip, color: domainOf(detailRequest.domain).color }">
                        <b>{{ detailRequest.domain }}</b> {{ domainOf(detailRequest.domain).label }}
                    </span>
                    <span class="mp-dm-item"><span class="mp-dm-lbl">Venue</span> {{ venueOf(detailRequest.venue).name }}</span>
                    <span class="mp-dm-item"><span class="mp-dm-lbl">Site</span> {{ detailRequest.site }}</span>
                    <span class="mp-dm-item"><span class="mp-dm-lbl">Value</span> <b class="mono">{{ fmtMoney(detailRequest.value) }}</b></span>
                </div>
            </div>
            <div class="mp-head-actions">
                <button class="mp-btn">Duplicate</button>
                <button class="mp-btn">Open change order</button>
                <button class="mp-btn mp-btn-primary">✓ Approve</button>
            </div>
        </div>

        <!-- Tabs -->
        <div class="mp-tabs">
            <button v-for="t in ['overview','items','change-order','audit']" :key="t"
                class="mp-tab"
                :class="{ 'mp-tab-on': detailTab === t }"
                @click="detailTab = t"
            >
                {{ t === 'overview' ? 'Overview' : t === 'items' ? `Items · ${detailLines.length}` : t === 'change-order' ? 'Change order · CO-001' : 'Audit log' }}
            </button>
        </div>

        <!-- Overview tab -->
        <div v-if="detailTab === 'overview'" class="mp-detail-grid">
            <div class="mp-card">
                <div class="mp-card-head"><h3 class="mp-card-title">Approval workflow</h3></div>
                <div class="mp-apv-list">
                    <div v-for="(step, i) in approvalWorkflow" :key="i"
                        class="mp-apv"
                        :class="'mp-apv-' + step.state"
                    >
                        <div class="mp-apv-rail">
                            <span class="mp-apv-dot" :style="{ background: stateStyles[step.state].dot }"/>
                        </div>
                        <div class="mp-apv-body">
                            <div class="mp-apv-head">
                                <span class="mp-avatar" :style="{ background: avatarColor(step.who) }">{{ step.who }}</span>
                                <div class="mp-apv-meta">
                                    <div class="mp-apv-name">{{ personOf(step.who).name }}</div>
                                    <div class="mp-apv-role">{{ step.role }}</div>
                                </div>
                                <span class="mp-apv-pill" :style="{ background: stateStyles[step.state].bg, color: stateStyles[step.state].fg }">
                                    {{ stateStyles[step.state].lbl }}
                                </span>
                            </div>
                            <div v-if="step.when" class="mp-apv-when">{{ step.when }}</div>
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
                        <tr><td class="mp-dm-lbl">ID</td><td class="mono">{{ detailRequest.id }}</td></tr>
                        <tr><td class="mp-dm-lbl">Submitted</td><td>{{ detailRequest.submitted }}</td></tr>
                        <tr><td class="mp-dm-lbl">Priority</td><td>{{ detailRequest.priority }}</td></tr>
                        <tr><td class="mp-dm-lbl">Items</td><td class="mono">{{ detailRequest.items }}</td></tr>
                        <tr><td class="mp-dm-lbl">Total value</td><td class="mono">{{ fmtMoney(detailRequest.value) }}</td></tr>
                        <tr><td class="mp-dm-lbl">Owner</td><td>{{ personOf(detailRequest.owner).name }}</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Items tab -->
        <div v-if="detailTab === 'items'" class="mp-card mp-card-flush">
            <table class="mp-dt">
                <thead><tr><th>SKU</th><th>Item</th><th class="ta-r">Qty</th><th>Unit</th><th class="ta-r">Rate</th><th class="ta-r">Total</th><th>Comment</th></tr></thead>
                <tbody>
                    <tr v-for="l in detailLines" :key="l.sku">
                        <td class="mono">{{ l.sku }}</td>
                        <td>{{ l.name }}</td>
                        <td class="ta-r mono">{{ l.qty }}</td>
                        <td class="mono">{{ l.unit }}</td>
                        <td class="ta-r mono">{{ fmtMoney(l.rate) }}</td>
                        <td class="ta-r mono">{{ fmtMoney(l.qty * l.rate) }}</td>
                        <td>{{ l.comment }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Change order tab -->
        <div v-if="detailTab === 'change-order'" class="mp-card mp-card-flush">
            <table class="mp-diff">
                <thead>
                    <tr>
                        <th>SKU</th><th>Item</th>
                        <th class="ta-r">Original qty</th><th class="ta-r">Proposed qty</th>
                        <th class="ta-r">Δ value</th><th>Reason</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(d, i) in diffLines" :key="i" :class="{ 'mp-diff-changed': d.changed }">
                        <td class="mono">{{ d.sku }}</td>
                        <td>
                            <div v-if="d.name !== d.after.name">
                                <div class="mp-diff-old">{{ d.name }}</div>
                                <div class="mp-diff-new">{{ d.after.name }}</div>
                            </div>
                            <span v-else>{{ d.name }}</span>
                        </td>
                        <td class="ta-r mono">
                            <span v-if="d.qty !== d.after.qty" class="mp-diff-old-inline">{{ d.qty }}</span>
                            <span v-else>{{ d.qty }}</span>
                        </td>
                        <td class="ta-r mono">
                            <span v-if="d.qty !== d.after.qty" class="mp-diff-new-inline">{{ d.after.qty }}</span>
                            <span v-else>{{ d.after.qty }}</span>
                        </td>
                        <td class="ta-r mono" :class="d.dValue > 0 ? 'mp-diff-pos' : d.dValue < 0 ? 'mp-diff-neg' : ''">
                            {{ d.dValue === 0 ? '—' : (d.dValue > 0 ? '+' : '') + fmtMoney(d.dValue) }}
                        </td>
                        <td class="mp-diff-reason">
                            <span v-if="i === 0">Increase footprint for broadcaster waiting pad</span>
                            <span v-else-if="i === 3">Add 6 chairs for athlete bench</span>
                            <span v-else-if="i === 5">Vendor substitution — equivalent specs, same rate</span>
                        </td>
                    </tr>
                </tbody>
                <tfoot>
                    <tr><td colspan="4" class="ta-r">Net change</td><td class="ta-r mono mp-diff-pos">+$1,930</td><td/></tr>
                </tfoot>
            </table>
        </div>

        <!-- Audit tab -->
        <div v-if="detailTab === 'audit'">
            <ul class="mp-audit">
                <li v-for="(e, i) in auditLog" :key="i" class="mp-audit-row">
                    <div class="mp-audit-at mono">{{ e.at }}</div>
                    <div class="mp-audit-rail"><span/></div>
                    <div class="mp-audit-body">
                        <div class="mp-audit-line">
                            <span class="mp-avatar mp-avatar-sm" :style="{ background: avatarColor(e.who) }">{{ e.who }}</span>
                            <span><b>{{ personOf(e.who).name }}</b> {{ e.verb }} <span v-if="e.what" class="mono mp-audit-what">{{ e.what }}</span></span>
                        </div>
                        <div v-if="e.note" class="mp-audit-note">{{ e.note }}</div>
                    </div>
                </li>
            </ul>
        </div>
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
.mp-detail-id { font-size: 13px; color: #76706a; font-weight: 400; margin-left: 10px; }
.mp-detail-meta { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; margin-top: 8px; }
.mp-dm-item { font-size: 12px; color: #76706a; }
.mp-dm-lbl { margin-right: 3px; }

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
.mp-dt td { padding: 11px 14px; border-bottom: 1px solid #f3f0ea; vertical-align: middle; color: #1a1614; }

.mp-diff { width: 100%; border-collapse: collapse; font-size: 13px; }
.mp-diff th { background: #fbfaf6; border-bottom: 1px solid #e8e4db; color: #76706a; font-size: 11px; text-transform: uppercase; letter-spacing: .05em; padding: 10px 14px; text-align: left; }
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
</style>
