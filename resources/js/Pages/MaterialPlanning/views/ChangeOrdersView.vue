<script setup>
import { ref, computed } from 'vue';

const props = defineProps({
    changeOrders: Array,
    coStates:     Object,
    requests:     Array,
    domains:      Array,
    venues:       Array,
    people:       Array,
    event:        { type: Object, default: () => ({ code: 'EVT' }) },
    showItemValues: { type: Boolean, default: false },
});

const emit = defineEmits(['open-request']);

// ── Filters ───────────────────────────────────────────────────────────────────
const state  = ref('all');
const domain = ref('all');
const venue  = ref('all');
const q      = ref('');

// Scoped to the active event first — change orders inherit their event from
// their parent request, same as Requests/Approvals.
const eventChangeOrders = computed(() =>
    props.event?.id ? props.changeOrders.filter(co => co.eventId === props.event.id) : props.changeOrders
);
const eventRequests = computed(() =>
    props.event?.id ? props.requests.filter(r => r.eventId === props.event.id) : props.requests
);

const reasons = computed(() => [...new Set(eventChangeOrders.value.map(r => r.reason))]);

const rows = computed(() => {
    let r = eventChangeOrders.value.slice();
    if (state.value  !== 'all') r = r.filter(x => x.state === state.value);
    if (domain.value !== 'all') r = r.filter(x => x.domain === domain.value);
    if (venue.value  !== 'all') r = r.filter(x => x.venue === venue.value);
    if (q.value) {
        const k = q.value.toLowerCase();
        r = r.filter(x => x.title.toLowerCase().includes(k) || x.id.toLowerCase().includes(k) || x.req.toLowerCase().includes(k));
    }
    return r;
});

function countOf(k) { return eventChangeOrders.value.filter(r => r.state === k).length; }

const openOnes = computed(() => eventChangeOrders.value.filter(r => r.state === 'pending'));
const pendingValue = computed(() => openOnes.value.reduce((s, r) => s + r.delta, 0));
const appliedDrift = computed(() => eventChangeOrders.value.filter(r => r.state === 'approved').reduce((s, r) => s + r.delta, 0));
const baseline = computed(() => eventRequests.value.reduce((s, r) => s + r.value, 0));
const driftPct = computed(() => baseline.value ? (appliedDrift.value / baseline.value) * 100 : 0);

const byDomain = computed(() => {
    const rows = props.domains.map(d => ({
        ...d,
        v: eventChangeOrders.value.filter(r => r.state === 'approved' && r.domain === d.code).reduce((s, r) => s + r.delta, 0),
    })).filter(x => x.v !== 0);
    return rows;
});
const maxAbs = computed(() => Math.max(1, ...byDomain.value.map(x => Math.abs(x.v))));

// ── Helpers ───────────────────────────────────────────────────────────────────
function domainOf(code)  { return props.domains.find(d => d.code === code) || props.domains[0]; }
function venueOf(code) { return props.venues.find(v => v.code === code) || props.venues[0]; }
function fmtMoney(n)   { return '$' + Number(n).toLocaleString('en-US'); }
function fmtDelta(v)   { return v === 0 ? '—' : (v > 0 ? '+' : '−') + fmtMoney(Math.abs(v)); }
function deltaClass(v) { return v > 0 ? 'diff-pos' : v < 0 ? 'diff-neg' : ''; }
function stateMeta(k)  { return props.coStates[k] || props.coStates.draft; }

const avatarColors = ['#7c2d12', '#0f766e', '#b45309', '#1d4ed8', '#6b21a8', '#155e75', '#854d0e'];
function avatarColor(initials) {
    const h = (initials.charCodeAt(0) + (initials.charCodeAt(1) || 0)) % avatarColors.length;
    return avatarColors[h];
}
</script>

<template>
    <div class="mp-page">
        <div class="mp-page-head">
            <div>
                <h1 class="mp-page-title">Change orders</h1>
                <p class="mp-page-sub">Every scope change raised against a baseline request in {{ event.code }}. Click a row to open its diff.</p>
            </div>
            <div class="mp-head-actions">
                <button class="mp-btn">Saved views</button>
                <button class="mp-btn">Export CSV</button>
            </div>
        </div>

        <!-- KPI + drift strip -->
        <div class="cox-strip">
            <div class="cox-kpi">
                <div class="cox-kpi-l">Open change orders</div>
                <div class="cox-kpi-v mono">{{ openOnes.length }}</div>
                <div class="cox-kpi-s">{{ openOnes.filter(r => r.stage === 'Finance').length }} at finance · oldest {{ openOnes[openOnes.length - 1]?.age }}</div>
            </div>
            <div class="cox-kpi">
                <div class="cox-kpi-l">Value in review</div>
                <div class="cox-kpi-v mono" :class="deltaClass(pendingValue)">{{ fmtDelta(pendingValue) }}</div>
                <div class="cox-kpi-s">net across {{ openOnes.length }} pending orders</div>
            </div>
            <div class="cox-kpi">
                <div class="cox-kpi-l">Applied drift</div>
                <div class="cox-kpi-v mono" :class="deltaClass(appliedDrift)">{{ fmtDelta(appliedDrift) }}</div>
                <div class="cox-kpi-s">{{ driftPct >= 0 ? '+' : '−' }}{{ Math.abs(driftPct).toFixed(2) }}% vs {{ fmtMoney(baseline) }} baseline</div>
            </div>
            <div class="cox-kpi">
                <div class="cox-kpi-l">Median cycle</div>
                <div class="cox-kpi-v mono">1.8 d</div>
                <div class="cox-kpi-s">raise → decision, last 30 days</div>
            </div>
            <div class="cox-drift">
                <div class="cox-kpi-l">Applied drift by domain</div>
                <ul class="cox-bars">
                    <li v-for="x in byDomain" :key="x.id" class="cox-bar">
                        <span class="cox-bar-l">{{ x.label }}</span>
                        <span class="cox-bar-track">
                            <span class="cox-bar-fill" :style="{ width: (Math.abs(x.v) / maxAbs) * 100 + '%', background: x.v < 0 ? '#a39d96' : x.color }"/>
                        </span>
                        <span class="mono" :class="deltaClass(x.v)">{{ fmtDelta(x.v) }}</span>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Filter bar -->
        <div class="filterbar">
            <div class="fb-search">
                <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5" stroke-linecap="round"/></svg>
                <input v-model="q" placeholder="Find by CO id, request id or title…"/>
            </div>
            <div class="fb-sel">
                <label>Domain</label>
                <select v-model="domain">
                    <option value="all">All</option>
                    <option v-for="d in domains" :key="d.id" :value="d.code">{{ d.label }}</option>
                </select>
            </div>
            <div class="fb-sel">
                <label>Venue</label>
                <select v-model="venue">
                    <option value="all">All venues</option>
                    <option v-for="v in venues" :key="v.code" :value="v.code">{{ v.name }}</option>
                </select>
            </div>
            <div class="fb-sel">
                <label>Reason</label>
                <select>
                    <option>Any reason</option>
                    <option v-for="r in reasons" :key="r">{{ r }}</option>
                </select>
            </div>
            <div class="fb-sel">
                <label>Raised</label>
                <select><option>Any time</option><option>Last 24h</option><option>Last week</option></select>
            </div>
        </div>

        <!-- Status chips -->
        <div class="chips">
            <button class="fchip" :class="{ 'fchip-active': state === 'all' }" @click="state = 'all'">All <span class="fchip-n">{{ eventChangeOrders.length }}</span></button>
            <button class="fchip" :class="{ 'fchip-active': state === 'pending' }" @click="state = 'pending'">Pending <span class="fchip-n">{{ countOf('pending') }}</span></button>
            <button class="fchip" :class="{ 'fchip-active': state === 'draft' }" @click="state = 'draft'">Draft <span class="fchip-n">{{ countOf('draft') }}</span></button>
            <button class="fchip" :class="{ 'fchip-active': state === 'approved' }" @click="state = 'approved'">Approved <span class="fchip-n">{{ countOf('approved') }}</span></button>
            <button class="fchip" :class="{ 'fchip-active': state === 'rejected' }" @click="state = 'rejected'">Rejected <span class="fchip-n">{{ countOf('rejected') }}</span></button>
        </div>

        <!-- Table -->
        <div class="mp-card mp-card-flush">
            <table class="mp-dt">
                <thead>
                    <tr>
                        <th>CO</th>
                        <th>Change</th>
                        <th>Request</th>
                        <th>Domain</th>
                        <th>Venue</th>
                        <th>Reason</th>
                        <th class="ta-r">Lines</th>
                        <th v-if="showItemValues" class="ta-r">Δ value</th>
                        <th>Status</th>
                        <th>Stage</th>
                        <th>Raised by</th>
                        <th>Raised</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="r in rows" :key="r.id" class="mp-dt-row" @click="emit('open-request', r.req)">
                        <td class="mono cox-id">{{ r.id }}</td>
                        <td><div class="mp-dt-title cox-title">{{ r.title }}</div></td>
                        <td class="mono cox-req">{{ r.req }}</td>
                        <td>
                            <span class="mp-dtag" :style="{ background: domainOf(r.domain).chip, color: domainOf(r.domain).color }">
                                <b>{{ r.domain }}</b>
                            </span>
                        </td>
                        <td><div class="mp-dt-venue">{{ venueOf(r.venue).name }}</div></td>
                        <td class="cox-reason">{{ r.reason }}</td>
                        <td class="ta-r mono">{{ r.rows }}</td>
                        <td v-if="showItemValues" class="ta-r mono" :class="deltaClass(r.delta)">{{ fmtDelta(r.delta) }}</td>
                        <td>
                            <span class="co-pill" :style="{ background: stateMeta(r.state).bg, color: stateMeta(r.state).fg }">
                                <span class="co-pill-dot" :style="{ background: stateMeta(r.state).dot }"/>
                                {{ stateMeta(r.state).label }}
                            </span>
                        </td>
                        <td class="cox-stage">{{ r.stage }}</td>
                        <td><span class="mp-avatar mp-avatar-sm" :style="{ background: avatarColor(r.raisedBy) }">{{ r.raisedBy }}</span></td>
                        <td class="mp-dt-when">{{ r.raisedOn }} · {{ r.age }}</td>
                        <td class="mp-dt-go">→</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="dt-foot">
            Showing <b>{{ rows.length }}</b> of {{ eventChangeOrders.length }} change orders · net applied drift
            <b class="mono" :class="deltaClass(appliedDrift)">{{ fmtDelta(appliedDrift) }}</b>
        </div>
    </div>
</template>

<style scoped>
/* ── Page shell ───────────────────────────────────────────────────────────── */
.mp-page { max-width: 100%; }
.mp-page-head {
    display: flex; justify-content: space-between; align-items: flex-end;
    gap: 24px; margin-bottom: 20px;
}
.mp-page-title { font-size: 24px; font-weight: 600; letter-spacing: -.02em; color: #1a1614; margin: 0; }
.mp-page-sub { font-size: 13px; color: #76706a; margin: 4px 0 0; }
.mp-head-actions { display: flex; gap: 8px; align-items: center; flex-shrink: 0; }

.mp-btn {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 7px 12px; border-radius: 6px;
    border: 1px solid #e8e4db; background: #fff;
    font-size: 12.5px; font-weight: 500; color: #1a1614; cursor: pointer; transition: background .12s, border-color .12s;
}
.mp-btn:hover { background: #fbfaf6; border-color: #3d3833; }

.mp-card {
    background: #fff; border: 1px solid #e8e4db; border-radius: 10px; margin-bottom: 16px;
    box-shadow: 0 1px 0 rgba(20,16,12,.03), 0 1px 2px rgba(20,16,12,.04);
}
.mp-card-flush { padding: 0; overflow: hidden; }

/* ── KPI + drift strip ────────────────────────────────────────────────────── */
.cox-strip {
    display: grid; grid-template-columns: repeat(4, 1fr) 1.6fr; gap: 0;
    background: #fff; border: 1px solid #e8e4db; border-radius: 10px;
    margin-bottom: 16px; box-shadow: 0 1px 0 rgba(20,16,12,.03), 0 1px 2px rgba(20,16,12,.04);
}
.cox-kpi { padding: 14px 18px; border-right: 1px solid #efece4; }
.cox-kpi-l { font-size: 11px; color: #76706a; text-transform: uppercase; letter-spacing: .06em; font-weight: 600; margin-bottom: 6px; }
.cox-kpi-v { font-size: 22px; font-weight: 700; letter-spacing: -.02em; color: #1a1614; }
.cox-kpi-s { font-size: 11px; color: #76706a; margin-top: 4px; line-height: 1.4; }
.cox-drift { padding: 14px 18px; }
.cox-bars { list-style: none; margin: 8px 0 0; padding: 0; display: flex; flex-direction: column; gap: 6px; }
.cox-bar { display: grid; grid-template-columns: 62px 1fr 70px; align-items: center; gap: 8px; font-size: 11.5px; }
.cox-bar-l { color: #3d3833; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.cox-bar-track { height: 6px; background: #efece4; border-radius: 3px; overflow: hidden; }
.cox-bar-fill { display: block; height: 100%; border-radius: 3px; }
.cox-bar .mono { text-align: right; font-size: 11px; }

/* ── Filter bar ───────────────────────────────────────────────────────────── */
.filterbar {
    display: grid; grid-template-columns: 1.6fr repeat(4, 1fr);
    gap: 8px; margin-bottom: 12px;
}
.fb-search {
    display: flex; align-items: center; gap: 8px;
    background: #fff; border: 1px solid #e8e4db; border-radius: 6px; padding: 6px 10px; color: #76706a;
}
.fb-search input { border: 0; outline: none; background: transparent; flex: 1; font-size: 12.5px; color: #1a1614; }
.fb-sel { display: flex; flex-direction: column; gap: 3px; }
.fb-sel label { font-size: 10.5px; color: #76706a; text-transform: uppercase; letter-spacing: .06em; padding-left: 2px; }
.fb-sel select {
    appearance: none;
    background: #fff url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24'%3E%3Cpath fill='none' stroke='%2376706a' stroke-width='2' stroke-linecap='round' stroke-linejoin='round' d='M6 9l6 6 6-6'/%3E%3C/svg%3E") no-repeat right 8px center;
    border: 1px solid #e8e4db; border-radius: 6px;
    padding: 6px 24px 6px 10px; font-size: 12.5px; color: #1a1614; cursor: pointer; outline: none;
}

/* ── Status chips ─────────────────────────────────────────────────────────── */
.chips { display: flex; gap: 6px; flex-wrap: wrap; margin-bottom: 12px; }
.fchip {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 5px 12px; border-radius: 20px; border: 1px solid #e8e4db;
    background: #fff; font-size: 12px; font-weight: 500; color: #545a6d; cursor: pointer; transition: background .12s;
}
.fchip:hover { background: #fbfaf6; }
.fchip-active { background: #1a1614; border-color: #1a1614; color: #fff; }
.fchip-n { font-weight: 700; }
.fchip-active .fchip-n { opacity: .8; }

/* ── Table ────────────────────────────────────────────────────────────────── */
.mp-dt { width: 100%; border-collapse: collapse; font-size: 13px; }
.mp-dt th {
    background: #fbfaf6; border-bottom: 1px solid #e8e4db;
    color: #76706a; font-size: 11px; text-transform: uppercase; letter-spacing: .05em;
    padding: 10px 14px; text-align: left; white-space: nowrap;
}
.mp-dt td { padding: 11px 14px; border-bottom: 1px solid #f3f0ea; vertical-align: middle; color: #1a1614; }
.mp-dt tr:last-child td { border-bottom: none; }
.mp-dt-row { cursor: pointer; transition: background .12s; }
.mp-dt-row:hover td { background: #fbfaf6; }
.mp-dt-title { font-weight: 500; }
.cox-id { color: #76706a; font-size: 12px; }
.cox-title { max-width: 320px; }
.cox-req { color: #76706a; }
.cox-reason { font-size: 12.5px; color: #3d3833; }
.cox-stage { font-size: 12.5px; color: #76706a; }
.mp-dt-venue { font-size: 13px; }
.mp-dt-when { font-size: 12px; color: #76706a; white-space: nowrap; }
.mp-dt-go { color: #76706a; font-size: 13px; }

.mp-dtag { display: inline-flex; align-items: center; gap: 4px; padding: 2px 8px; border-radius: 5px; font-size: 12px; font-weight: 600; }
.co-pill {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 3px 9px; border-radius: 20px; font-size: 12px; font-weight: 600; white-space: nowrap;
}
.co-pill-dot { width: 6px; height: 6px; border-radius: 50%; flex-shrink: 0; }
.mp-avatar {
    display: inline-flex; align-items: center; justify-content: center;
    width: 26px; height: 26px; border-radius: 50%;
    color: #fff; font-size: 10.5px; font-weight: 700; flex-shrink: 0;
}
.mp-avatar-sm { width: 22px; height: 22px; font-size: 9px; }

.diff-pos { color: #166534; }
.diff-neg { color: #991b1b; }

.dt-foot { font-size: 12px; color: #76706a; text-align: right; margin: 8px 0 16px; }

.mono { font-family: ui-monospace, 'SF Mono', Menlo, monospace; }
.ta-r { text-align: right; }
</style>
