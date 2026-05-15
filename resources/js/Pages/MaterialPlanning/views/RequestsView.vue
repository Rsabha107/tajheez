<script setup>
import { ref, computed } from 'vue';

const props = defineProps({
    requests:    Array,
    domains:     Array,
    venues:      Array,
    statuses:    Object,
    approvalOnly: { type: Boolean, default: false },
    people:      Array,
});

const emit = defineEmits(['open-request', 'go-to']);

// ── Local state ───────────────────────────────────────────────────────────────
const reqFilter = ref('all');
const reqDomain = ref('all');
const reqVenue  = ref('all');
const reqSearch = ref('');

const filteredRequests = computed(() => {
    let rows = props.requests.slice();
    if (props.approvalOnly) rows = rows.filter(r => ['submitted','l1','l2','finance','changed'].includes(r.status));
    if (reqFilter.value !== 'all') rows = rows.filter(r => r.status === reqFilter.value);
    if (reqDomain.value !== 'all') rows = rows.filter(r => r.domain === reqDomain.value);
    if (reqVenue.value  !== 'all') rows = rows.filter(r => r.venue  === reqVenue.value);
    if (reqSearch.value) {
        const q = reqSearch.value.toLowerCase();
        rows = rows.filter(r => r.title.toLowerCase().includes(q) || r.id.toLowerCase().includes(q));
    }
    return rows;
});

const statusCounts = computed(() => {
    const r = props.requests;
    return {
        all:       r.length,
        submitted: r.filter(x => x.status === 'submitted').length,
        l1:        r.filter(x => x.status === 'l1').length,
        l2:        r.filter(x => x.status === 'l2').length,
        finance:   r.filter(x => x.status === 'finance').length,
        changed:   r.filter(x => x.status === 'changed').length,
        approved:  r.filter(x => x.status === 'approved').length,
    };
});

const priorityColors = { Critical: '#991b1b', High: '#b45309', Medium: '#6b7280', Low: '#9ca3af' };

// ── Helpers ───────────────────────────────────────────────────────────────────
function domainOf(id)  { return props.domains.find(d => d.id === id) || props.domains[0]; }
function venueOf(code) { return props.venues.find(v => v.code === code) || props.venues[0]; }
function fmtMoney(n)   { return '$' + Number(n).toLocaleString('en-US'); }

const avatarColors = ['#7c2d12','#0f766e','#b45309','#1d4ed8','#6b21a8','#155e75','#854d0e'];
function avatarColor(initials) {
    const h = (initials.charCodeAt(0) + (initials.charCodeAt(1) || 0)) % avatarColors.length;
    return avatarColors[h];
}
</script>

<template>
    <div class="mp-page">
        <div class="mp-page-head">
            <div>
                <h1 class="mp-page-title">{{ approvalOnly ? 'Approvals' : 'Requests' }}</h1>
                <p class="mp-page-sub">
                    {{ approvalOnly ? 'Items routed to you across L1, Category and Finance gates.' : 'All material requests for this event. Click any row to open.' }}
                </p>
            </div>
            <div class="mp-head-actions">
                <button class="mp-btn">Export CSV</button>
                <button class="mp-btn mp-btn-primary" @click="emit('go-to', 'new')">+ New request</button>
            </div>
        </div>

        <!-- Approval summary bar -->
        <div v-if="approvalOnly" class="mp-approvalbar">
            <div class="mp-ab-stat"><div class="mp-ab-n">7</div><div class="mp-ab-l">need your action</div></div>
            <div class="mp-ab-stat"><div class="mp-ab-n">2</div><div class="mp-ab-l">change orders</div></div>
            <div class="mp-ab-stat"><div class="mp-ab-n">$412,810</div><div class="mp-ab-l">pending value</div></div>
            <div class="mp-ab-stat mp-ab-warn"><div class="mp-ab-n">3</div><div class="mp-ab-l">past 48h SLA</div></div>
            <div class="mp-ab-actions">
                <button class="mp-btn mp-btn-sm">Bulk approve…</button>
                <button class="mp-btn mp-btn-primary mp-btn-sm">Open next →</button>
            </div>
        </div>

        <!-- Filter bar -->
        <div class="mp-filterbar">
            <div class="mp-fb-search">
                <i class="bx bx-search"></i>
                <input v-model="reqSearch" placeholder="Find request by ID or title…"/>
            </div>
            <div class="mp-fb-sel">
                <label>Domain</label>
                <select v-model="reqDomain">
                    <option value="all">All</option>
                    <option v-for="d in domains" :key="d.id" :value="d.id">{{ d.label }}</option>
                </select>
            </div>
            <div class="mp-fb-sel">
                <label>Venue</label>
                <select v-model="reqVenue">
                    <option value="all">All venues</option>
                    <option v-for="v in venues" :key="v.code" :value="v.code">{{ v.name }}</option>
                </select>
            </div>
        </div>

        <!-- Status chips -->
        <div v-if="!approvalOnly" class="mp-chips">
            <button v-for="(count, key) in statusCounts" :key="key"
                class="mp-chip"
                :class="{ 'mp-chip-active': reqFilter === key }"
                @click="reqFilter = key"
            >
                {{ key === 'all' ? 'All' : statuses[key]?.label || key }}
                <span class="mp-chip-n">{{ count }}</span>
            </button>
        </div>

        <!-- Table -->
        <div class="mp-card mp-card-flush">
            <table class="mp-dt">
                <thead>
                    <tr>
                        <th><input type="checkbox"/></th>
                        <th>ID</th>
                        <th>Title</th>
                        <th>Domain</th>
                        <th>Venue · Site</th>
                        <th class="ta-r">Items</th>
                        <th class="ta-r">Value</th>
                        <th>Status</th>
                        <th>Priority</th>
                        <th>Owner</th>
                        <th>Updated</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="r in filteredRequests" :key="r.id"
                        class="mp-dt-row"
                        @click="emit('open-request', r.id)"
                    >
                        <td @click.stop><input type="checkbox"/></td>
                        <td class="mono mp-dt-id">{{ r.id }}</td>
                        <td class="mp-dt-title">{{ r.title }}</td>
                        <td>
                            <span class="mp-dtag" :style="{ background: domainOf(r.domain).chip, color: domainOf(r.domain).color }">
                                <b>{{ r.domain }}</b>
                            </span>
                        </td>
                        <td>
                            <div class="mp-dt-venue">{{ venueOf(r.venue).name }}</div>
                            <div class="mp-dt-site">{{ r.site }}</div>
                        </td>
                        <td class="ta-r mono">{{ r.items }}</td>
                        <td class="ta-r mono">{{ fmtMoney(r.value) }}</td>
                        <td>
                            <span class="mp-pill" :style="{ background: statuses[r.status]?.bg, color: statuses[r.status]?.fg }">
                                <span class="mp-pill-dot" :style="{ background: statuses[r.status]?.dot }"/>
                                {{ statuses[r.status]?.label }}
                            </span>
                        </td>
                        <td>
                            <span class="mp-prio">
                                <span class="mp-prio-dot" :style="{ background: priorityColors[r.priority] }"/>
                                {{ r.priority }}
                            </span>
                        </td>
                        <td>
                            <span class="mp-avatar mp-avatar-sm" :style="{ background: avatarColor(r.owner) }">{{ r.owner }}</span>
                        </td>
                        <td class="mp-dt-when">{{ r.updated }}</td>
                        <td class="mp-dt-go">→</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="mp-dt-foot">
            Showing <b>{{ filteredRequests.length }}</b> of {{ requests.length }} requests
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

.mp-approvalbar {
    display: flex; align-items: center; gap: 20px;
    background: #fff; border: 1px solid #e8e4db; border-radius: 8px;
    padding: 12px 18px; margin-bottom: 14px;
}
.mp-ab-stat { text-align: center; }
.mp-ab-n { font-size: 20px; font-weight: 700; color: #1a1614; }
.mp-ab-l { font-size: 11px; color: #76706a; }
.mp-ab-warn .mp-ab-n { color: #991b1b; }
.mp-ab-actions { display: flex; gap: 8px; margin-left: auto; }

.mp-filterbar { display: flex; align-items: center; gap: 12px; margin-bottom: 12px; flex-wrap: wrap; }
.mp-fb-search {
    display: flex; align-items: center; gap: 7px;
    background: #fff; border: 1px solid #e8e4db; border-radius: 7px;
    padding: 7px 12px; flex: 1; min-width: 220px;
}
.mp-fb-search input { border: none; outline: none; font-size: 13px; background: transparent; flex: 1; }
.mp-fb-sel { display: flex; align-items: center; gap: 6px; font-size: 12px; color: #76706a; }
.mp-fb-sel select { border: 1px solid #e8e4db; border-radius: 6px; padding: 5px 8px; font-size: 12px; background: #fff; }

.mp-chips { display: flex; gap: 6px; flex-wrap: wrap; margin-bottom: 12px; }
.mp-chip {
    padding: 5px 12px; border-radius: 20px; border: 1px solid #e8e4db;
    background: #fff; font-size: 12px; color: #545a6d; cursor: pointer;
    display: flex; align-items: center; gap: 5px;
}
.mp-chip:hover { background: #f6f5f1; }
.mp-chip-active { background: rgba(15,118,110,.12); border-color: #0f766e; color: #0f766e; font-weight: 600; }
.mp-chip-n { font-weight: 700; }

.mp-card { background: #fff; border: 1px solid #e8e4db; border-radius: 10px; padding: 16px 20px; margin-bottom: 14px; }
.mp-card-flush { padding: 0; overflow: hidden; }

.mp-dt { width: 100%; border-collapse: collapse; font-size: 13px; }
.mp-dt th {
    background: #fbfaf6; border-bottom: 1px solid #e8e4db;
    color: #76706a; font-size: 11px; text-transform: uppercase; letter-spacing: .05em;
    padding: 10px 14px; text-align: left; white-space: nowrap;
}
.mp-dt td { padding: 11px 14px; border-bottom: 1px solid #f3f0ea; vertical-align: middle; color: #1a1614; }
.mp-dt-row { cursor: pointer; transition: background .12s; }
.mp-dt-row:hover td { background: #fbfaf6; }
.mp-dt-id { color: #76706a; font-size: 12px; }
.mp-dt-title { font-weight: 500; }
.mp-dt-venue { font-size: 13px; }
.mp-dt-site { font-size: 11px; color: #76706a; }
.mp-dt-when { font-size: 12px; color: #76706a; }
.mp-dt-go { color: #76706a; font-size: 13px; }
.mp-dt-foot { font-size: 12px; color: #76706a; text-align: right; margin-top: 8px; }

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
.mp-prio { display: inline-flex; align-items: center; gap: 5px; font-size: 12px; }
.mp-prio-dot { width: 7px; height: 7px; border-radius: 50%; }
.mp-avatar {
    display: inline-flex; align-items: center; justify-content: center;
    width: 28px; height: 28px; border-radius: 50%;
    color: #fff; font-size: 11px; font-weight: 700; flex-shrink: 0;
}
.mp-avatar-sm { width: 22px; height: 22px; font-size: 9px; }

.mono { font-family: 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace; }
.ta-r { text-align: right; }
</style>
