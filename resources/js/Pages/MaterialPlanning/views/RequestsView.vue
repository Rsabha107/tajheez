<script setup>
import { ref, computed, watch } from 'vue';
import axios from 'axios';
import ConfirmModal from '../components/ConfirmModal.vue';
import RefreshButton from '../components/RefreshButton.vue';

const props = defineProps({
    requests:    Array,
    domains:     Array,
    venues:      Array,
    statuses:    Object,
    approvalOnly: { type: Boolean, default: false },
    people:      Array,
    functionalAreas: { type: Array, default: () => [] },
    refreshing:  { type: Boolean, default: false },
    showItemValues: { type: Boolean, default: false },
    event:       { type: Object, default: null },
});

const emit = defineEmits(['open-request', 'go-to', 'requests-deleted', 'refresh']);

// ── Local state ───────────────────────────────────────────────────────────────
const reqFilter = ref('all');
const reqDomain = ref('all');
const reqVenue  = ref('all');
const reqFA     = ref('all');
const reqSearch = ref('');

// ── Selection & bulk delete ──────────────────────────────────────────────────
const selectedIds = ref(new Set());
watch(() => props.requests, () => {
    selectedIds.value = new Set();
    expandedIds.value = new Set();
    expandedData.value = {};
    expandedError.value = {};
});

function toggleRow(id) {
    const next = new Set(selectedIds.value);
    next.has(id) ? next.delete(id) : next.add(id);
    selectedIds.value = next;
}
function toggleSelectAll() {
    selectedIds.value = allSelected.value
        ? new Set()
        : new Set(filteredRequests.value.map(r => r.id));
}

// ── Expand row to show line items ────────────────────────────────────────────
const expandedIds = ref(new Set());
const expandedData = ref({});   // code -> detail response ({ lines: [...] })
const expandedLoading = ref(new Set());
const expandedError = ref({});  // code -> error message

async function toggleExpand(r) {
    const next = new Set(expandedIds.value);
    if (next.has(r.id)) {
        next.delete(r.id);
        expandedIds.value = next;
        return;
    }
    next.add(r.id);
    expandedIds.value = next;

    if (expandedData.value[r.id] || expandedLoading.value.has(r.id)) return;

    const loading = new Set(expandedLoading.value);
    loading.add(r.id);
    expandedLoading.value = loading;
    try {
        const { data } = await axios.get(route('mp.requests.show', r.id));
        expandedData.value = { ...expandedData.value, [r.id]: data };
    } catch (e) {
        expandedError.value = { ...expandedError.value, [r.id]: 'Could not load items for this request.' };
    } finally {
        const done = new Set(expandedLoading.value);
        done.delete(r.id);
        expandedLoading.value = done;
    }
}

const showDeleteConfirm = ref(false);
const deleting = ref(false);
const deleteError = ref(null);

function confirmDeleteSelected() {
    deleteError.value = null;
    showDeleteConfirm.value = true;
}

async function deleteSelected() {
    const codes = [...selectedIds.value];
    if (!codes.length) return;

    deleting.value = true;
    deleteError.value = null;
    try {
        await axios.delete(route('mp.requests.bulk-destroy'), { data: { codes } });
        selectedIds.value = new Set();
        showDeleteConfirm.value = false;
        emit('requests-deleted');
    } catch (e) {
        deleteError.value = e.response?.status === 403
            ? "You don't have permission to delete these requests."
            : 'Could not delete the selected requests.';
    } finally {
        deleting.value = false;
    }
}

const statusFilters = [
    { key: 'all',       label: 'All' },
    { key: 'submitted', label: 'Submitted' },
    { key: 'l1',        label: 'L1 review' },
    { key: 'l2',        label: 'Category' },
    { key: 'finance',   label: 'Finance' },
    { key: 'changed',   label: 'Change req' },
    { key: 'approved',  label: 'Approved' },
];

// Scoped to the active event first — everything else (status chips, filters,
// counts) operates on this, not the raw `requests` prop, which spans every event.
const eventRequests = computed(() =>
    props.event?.id ? props.requests.filter(r => r.eventId === props.event.id) : props.requests
);

const filteredRequests = computed(() => {
    let rows = eventRequests.value.slice();
    if (props.approvalOnly) rows = rows.filter(r => ['submitted','l1','l2','finance','changed'].includes(r.status));
    if (reqFilter.value !== 'all') rows = rows.filter(r => r.status === reqFilter.value);
    if (reqDomain.value !== 'all') rows = rows.filter(r => r.domain === reqDomain.value);
    if (reqVenue.value  !== 'all') rows = rows.filter(r => r.venue  === reqVenue.value);
    if (reqFA.value     !== 'all') rows = rows.filter(r => r.functionalArea === reqFA.value);
    if (reqSearch.value) {
        const q = reqSearch.value.toLowerCase();
        rows = rows.filter(r => r.title.toLowerCase().includes(q) || r.id.toLowerCase().includes(q));
    }
    return rows;
});

const allSelected = computed(() =>
    filteredRequests.value.length > 0 && filteredRequests.value.every(r => selectedIds.value.has(r.id))
);
const someSelected = computed(() => selectedIds.value.size > 0 && !allSelected.value);

const statusCounts = computed(() => {
    const r = eventRequests.value;
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

// ── Helpers ───────────────────────────────────────────────────────────────────
function domainOf(code)  { return props.domains.find(d => d.code === code) || props.domains[0]; }
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
                    <template v-if="approvalOnly">{{ filteredRequests.length }} items routed to you across L1, Category and Finance gates.</template>
                    <template v-else>{{ eventRequests.length }} material request{{ eventRequests.length === 1 ? '' : 's' }} for this event. Click any row to open.</template>
                </p>
            </div>
            <div class="mp-head-actions">
                <RefreshButton :refreshing="refreshing" @click="emit('refresh')"/>
                <button class="mp-btn"><i class="bx bx-filter-alt"></i> Saved views</button>
                <button class="mp-btn"><i class="bx bx-download"></i> Export CSV</button>
                <button class="mp-btn mp-btn-primary" @click="emit('go-to', 'new')"><i class="bx bx-plus"></i> New request</button>
            </div>
        </div>

        <!-- Approval summary bar -->
        <div v-if="approvalOnly" class="mp-approvalbar">
            <div class="mp-ab-stat"><div class="mp-ab-n">7</div><div class="mp-ab-l">need your action</div></div>
            <div class="mp-ab-stat"><div class="mp-ab-n">2</div><div class="mp-ab-l">change orders</div></div>
            <div class="mp-ab-stat"><div class="mp-ab-n">$412,810</div><div class="mp-ab-l">pending value</div></div>
            <div class="mp-ab-stat mp-ab-warn"><div class="mp-ab-n">3</div><div class="mp-ab-l">past 48h SLA</div></div>
            <div class="mp-ab-actions">
                <button class="mp-btn mp-btn-primary mp-btn-sm">Open next →</button>
            </div>
        </div>

        <!-- Filter bar -->
        <div class="mp-filterbar">
            <div class="mp-fb-search">
                <i class="bx bx-search"></i>
                <input v-model="reqSearch" placeholder="Find request by id or title…"/>
            </div>
            <div class="mp-fb-sel">
                <label>Domain</label>
                <select v-model="reqDomain">
                    <option value="all">All</option>
                    <option v-for="d in domains" :key="d.id" :value="d.code">{{ d.label }}</option>
                </select>
            </div>
            <div class="mp-fb-sel">
                <label>Venue</label>
                <select v-model="reqVenue">
                    <option value="all">All venues</option>
                    <option v-for="v in venues" :key="v.code" :value="v.code">{{ v.name }}</option>
                </select>
            </div>
            <div class="mp-fb-sel">
                <label>Functional Area</label>
                <select v-model="reqFA">
                    <option value="all">All</option>
                    <option v-for="fa in functionalAreas" :key="fa.id" :value="fa.code">{{ fa.code }}</option>
                </select>
            </div>
            <div class="mp-fb-sel">
                <label>Owner</label>
                <select>
                    <option>Anyone</option>
                    <option v-for="p in people" :key="p.initials">{{ p.name }}</option>
                </select>
            </div>
            <div class="mp-fb-sel">
                <label>Updated</label>
                <select>
                    <option>Any time</option>
                    <option>Last 24h</option>
                    <option>Last week</option>
                </select>
            </div>
        </div>

        <!-- Status chips -->
        <div v-if="!approvalOnly" class="mp-chips">
            <button v-for="f in statusFilters" :key="f.key"
                class="mp-chip"
                :class="{ 'mp-chip-active': reqFilter === f.key }"
                @click="reqFilter = f.key"
            >
                {{ f.label }}
                <span class="mp-chip-n">{{ statusCounts[f.key] }}</span>
            </button>
        </div>

        <!-- Bulk actions -->
        <div v-if="selectedIds.size > 0" class="mp-bulkbar">
            <span class="mp-bulkbar-n">{{ selectedIds.size }} selected</span>
            <button class="mp-btn mp-btn-sm" @click="selectedIds = new Set()">Clear</button>
            <button v-if="approvalOnly" class="mp-btn mp-btn-primary mp-btn-sm">Bulk approve…</button>
            <button class="mp-btn mp-btn-danger mp-btn-sm" @click="confirmDeleteSelected">
                <i class="bx bx-trash"></i> Delete
            </button>
        </div>

        <ConfirmModal
            v-if="showDeleteConfirm"
            :title="`Delete ${selectedIds.size} request${selectedIds.size > 1 ? 's' : ''}?`"
            message="This cannot be undone."
            confirm-text="Delete"
            loading-text="Deleting…"
            :loading="deleting"
            danger
            @cancel="showDeleteConfirm = false"
            @confirm="deleteSelected"
        >
            <p v-if="deleteError" class="cfm-err">{{ deleteError }}</p>
        </ConfirmModal>

        <!-- Table -->
        <div class="mp-card mp-card-flush">
            <table class="mp-dt">
                <thead>
                    <tr>
                        <th><input type="checkbox" :checked="allSelected" :indeterminate="someSelected" @change="toggleSelectAll"/></th>
                        <th>ID</th>
                        <th>Venue · Site</th>
                        <th class="ta-c">Items</th>
                        <th v-if="showItemValues" class="ta-c">Value</th>
                        <th>Status</th>
                        <th>Functional Area</th>
                        <th>Owner</th>
                        <th>Updated</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <template v-for="r in filteredRequests" :key="r.id">
                    <tr
                        class="mp-dt-row"
                        :class="{ 'mp-dt-row-expanded': expandedIds.has(r.id) }"
                        @click="toggleExpand(r)"
                    >
                        <td @click.stop><input type="checkbox" :checked="selectedIds.has(r.id)" @change="toggleRow(r.id)"/></td>
                        <td class="mono mp-dt-id">
                            <i class="bx mp-dt-chevron" :class="expandedIds.has(r.id) ? 'bx-chevron-down' : 'bx-chevron-right'"></i>
                            {{ r.id }}
                            <i v-if="r.hasServiceOption" class="bx bxs-truck mp-dt-svc-mark" title="One or more items has a service assigned"></i>
                        </td>
                        <td>
                            <div class="mp-dt-venue">{{ venueOf(r.venue).name }}</div>
                            <div class="mp-dt-site">{{ r.site }}</div>
                        </td>
                        <td class="ta-c mono">{{ r.items }}</td>
                        <td v-if="showItemValues" class="ta-c mono">{{ fmtMoney(r.value) }}</td>
                        <td>
                            <span class="mp-pill" :style="{ background: statuses[r.status]?.bg, color: statuses[r.status]?.fg }">
                                <span class="mp-pill-dot" :style="{ background: statuses[r.status]?.dot }"/>
                                {{ statuses[r.status]?.label }}
                            </span>
                        </td>
                        <td>
                            <span v-if="r.functionalArea" class="mp-fa-tag">{{ r.functionalArea }}</span>
                            <span v-else class="mp-muted">—</span>
                        </td>
                        <td>
                            <span class="mp-avatar mp-avatar-sm" :style="{ background: avatarColor(r.owner) }">{{ r.owner }}</span>
                        </td>
                        <td class="mp-dt-when">{{ r.updated }}</td>
                        <td class="mp-dt-go" @click.stop>
                            <button
                                v-if="r.status === 'draft'"
                                class="mp-icon-btn mp-icon-edit"
                                title="Edit draft"
                                @click="emit('go-to', 'new', { editCode: r.id })"
                            ><i class="bx bx-pencil"></i></button>
                            <button
                                class="mp-icon-btn"
                                title="Open full detail"
                                @click="emit('open-request', r.id)"
                            ><i class="bx bx-link-external"></i></button>
                        </td>
                    </tr>
                    <tr v-if="expandedIds.has(r.id)" class="mp-dt-expand-row">
                        <td colspan="10">
                            <div v-if="expandedLoading.has(r.id)" class="mp-dt-expand-status">Loading items…</div>
                            <div v-else-if="expandedError[r.id]" class="mp-dt-expand-status mp-dt-expand-error">{{ expandedError[r.id] }}</div>
                            <div v-else-if="!expandedData[r.id]?.lines?.length" class="mp-dt-expand-status">No line items on this request.</div>
                            <table v-else class="mp-dt-expand-table">
                                <thead>
                                    <tr>
                                        <th>SKU</th><th>Item</th><th>Domain</th><th class="ta-c">Qty</th><th>Unit</th>
                                        <th v-if="showItemValues" class="ta-c">Rate</th><th v-if="showItemValues" class="ta-c">Total</th><th>Service option</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="l in expandedData[r.id].lines" :key="l.id">
                                        <td class="mono">{{ l.sku }}</td>
                                        <td>{{ l.name }}</td>
                                        <td>
                                            <span v-if="l.domain" class="mp-dtag" :style="{ background: domainOf(l.domain).chip, color: domainOf(l.domain).color }">
                                                <b>{{ l.domain }}</b>
                                            </span>
                                            <span v-else class="mp-muted">—</span>
                                        </td>
                                        <td class="ta-c mono">{{ l.qty }}</td>
                                        <td class="mono">{{ l.unit }}</td>
                                        <td v-if="showItemValues" class="ta-c mono">{{ fmtMoney(l.rate) }}</td>
                                        <td v-if="showItemValues" class="ta-c mono">{{ fmtMoney(l.value) }}</td>
                                        <td>{{ l.serviceOptionName || '—' }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </td>
                    </tr>
                    </template>
                </tbody>
            </table>
        </div>
        <div class="mp-dt-foot">
            Showing <b>{{ filteredRequests.length }}</b> of {{ eventRequests.length }} requests
        </div>
    </div>
</template>

<style scoped>
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
    font-size: 12.5px; font-weight: 500; color: #1a1614; cursor: pointer;
    transition: background .12s, border-color .12s;
}
.mp-btn:hover { background: #fbfaf6; border-color: #3d3833; }
.mp-btn-primary { background: #1a1614; border-color: #1a1614; color: #fff; }
.mp-btn-primary:hover { background: #0a0806; border-color: #0a0806; }
.mp-btn-danger { background: #fff; border-color: #f3c9c9; color: #991b1b; }
.mp-btn-danger:hover { background: #fdecec; border-color: #991b1b; }
.mp-btn-danger:disabled { opacity: .6; cursor: default; }
.mp-btn-sm { padding: 4px 10px; font-size: 12px; }
.cfm-err { font-size: 12.5px; color: #991b1b; margin: 10px 0 0; }

.mp-bulkbar {
    display: flex; align-items: center; gap: 10px;
    background: #fbfaf6; border: 1px solid #e8e4db; border-radius: 8px;
    padding: 8px 14px; margin-bottom: 12px;
}
.mp-bulkbar-n { font-size: 12.5px; font-weight: 600; color: #1a1614; margin-right: 4px; }

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

.mp-filterbar {
    display: grid; grid-template-columns: 1.6fr repeat(5, 1fr);
    gap: 8px; margin-bottom: 12px;
}
.mp-fb-search {
    display: flex; align-items: center; gap: 8px;
    background: #fff; border: 1px solid #e8e4db; border-radius: 6px;
    padding: 6px 10px; color: #76706a;
}
.mp-fb-search input { border: none; outline: none; font-size: 12.5px; background: transparent; flex: 1; color: #1a1614; }
.mp-fb-sel { display: flex; flex-direction: column; gap: 3px; }
.mp-fb-sel label { font-size: 10.5px; color: #76706a; text-transform: uppercase; letter-spacing: .06em; padding-left: 2px; }
.mp-fb-sel select {
    appearance: none;
    background: #fff url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24'%3E%3Cpath fill='none' stroke='%2376706a' stroke-width='2' stroke-linecap='round' stroke-linejoin='round' d='M6 9l6 6 6-6'/%3E%3C/svg%3E") no-repeat right 8px center;
    border: 1px solid #e8e4db; border-radius: 6px;
    padding: 6px 24px 6px 10px; font-size: 12.5px; color: #1a1614; cursor: pointer;
}

.mp-chips { display: flex; gap: 6px; flex-wrap: wrap; margin-bottom: 12px; }
.mp-chip {
    padding: 5px 10px; border-radius: 5px; border: 1px solid #e8e4db;
    background: #fff; font-size: 12px; color: #3d3833; cursor: pointer;
    display: flex; align-items: center; gap: 6px; transition: border-color .12s;
}
.mp-chip:hover { border-color: #3d3833; }
.mp-chip-active { background: #1a1614; border-color: #1a1614; color: #fff; }
.mp-chip-n { font-family: ui-monospace, 'SF Mono', Menlo, monospace; font-size: 11px; opacity: .7; }

.mp-card { background: #fff; border: 1px solid #e8e4db; border-radius: 10px; padding: 16px 20px; margin-bottom: 14px; }
.mp-card-flush { padding: 0; overflow: hidden; }

.mp-dt { width: 100%; border-collapse: collapse; font-size: 13px; }
.mp-dt th {
    background: #fbfaf6; border-bottom: 1px solid #e8e4db;
    color: #76706a; font-size: 11px; text-transform: uppercase; letter-spacing: .05em;
    padding: 10px 14px; text-align: left; white-space: nowrap;
}
.mp-dt th.ta-c { text-align: center; }
.mp-dt td { padding: 11px 14px; border-bottom: 1px solid #f3f0ea; vertical-align: middle; color: #1a1614; }
.mp-dt-row { cursor: pointer; transition: background .12s; }
.mp-dt-row:hover td { background: #fbfaf6; }
.mp-dt-id { color: #76706a; font-size: 12px; }
.mp-dt-venue { font-size: 13px; }
.mp-dt-site { font-size: 11px; color: #76706a; }
.mp-dt-when { font-size: 12px; color: #76706a; }
.mp-dt-go { color: #76706a; font-size: 13px; text-align: right; display: flex; gap: 4px; justify-content: flex-end; }
.mp-icon-btn { width: 28px; height: 28px; border-radius: 6px; border: 1px solid #e8e4db; background: #fff; color: #76706a; display: inline-flex; align-items: center; justify-content: center; cursor: pointer; font-size: 13px; transition: background .15s; }
.mp-icon-btn:hover { background: #fbfaf6; color: #1a1614; }
.mp-icon-edit { background: #fff7e6; border-color: #fde7b0; color: #d97706; }
.mp-icon-edit:hover { background: #fef3c7; }
.mp-dt-foot { font-size: 12px; color: #76706a; text-align: right; margin-top: 8px; }

.mp-dt-chevron { font-size: 15px; vertical-align: -2px; margin-right: 2px; color: #a39d96; }
.mp-dt-svc-mark { font-size: 13px; vertical-align: -1px; margin-left: 5px; color: #0f766e; }
.mp-dt-row-expanded td { background: #fbfaf6; }
.mp-dt-expand-row td { padding: 0; border-bottom: 1px solid #f3f0ea; }
.mp-dt-expand-status { padding: 16px 24px; font-size: 12.5px; color: #76706a; }
.mp-dt-expand-error { color: #991b1b; }
.mp-dt-expand-table { width: 100%; border-collapse: collapse; font-size: 12.5px; background: #fbfaf6; }
.mp-dt-expand-table th {
    background: #f3f0e8; color: #76706a; font-size: 10.5px; text-transform: uppercase; letter-spacing: .04em;
    padding: 7px 24px; text-align: left; white-space: nowrap;
}
.mp-dt-expand-table th.ta-c { text-align: center; }
.mp-dt-expand-table td { padding: 8px 24px; border-top: 1px solid #efece4; color: #1a1614; }
.mp-dt-expand-table td.ta-c { text-align: center; }
.mp-dt-expand-table td:first-child, .mp-dt-expand-table th:first-child { padding-left: 24px; }

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
.mp-fa-tag { font-size: 12px; color: #3d3833; background: #f6f5f1; padding: 2px 8px; border-radius: 5px; }
.mp-muted { color: #a39d96; font-size: 12px; }
.mp-avatar {
    display: inline-flex; align-items: center; justify-content: center;
    width: 28px; height: 28px; border-radius: 50%;
    color: #fff; font-size: 11px; font-weight: 700; flex-shrink: 0;
}
.mp-avatar-sm { width: 22px; height: 22px; font-size: 9px; }

.mono { font-family: 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace; }
.ta-r { text-align: right; }
.ta-c { text-align: center; }
</style>
