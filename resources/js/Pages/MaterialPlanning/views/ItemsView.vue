<script setup>
import { ref, computed, watch } from 'vue';
import axios from 'axios';
import BulkAssignServiceOptionModal from '../components/BulkAssignServiceOptionModal.vue';
import FilterPanel from '../components/FilterPanel.vue';

const props = defineProps({
    requestLines:    { type: Array, default: () => [] },
    domains:         { type: Array, default: () => [] },
    venues:          { type: Array, default: () => [] },
    functionalAreas: { type: Array, default: () => [] },
    people:          { type: Array, default: () => [] },
    spaces:          { type: Array, default: () => [] },
    areas:           { type: Array, default: () => [] },
    serviceOptions:  { type: Array, default: () => [] },
    suppliers:       { type: Array, default: () => [] },
    permissions:     { type: Object, default: () => ({ isAdmin: false, managedDomain: null }) },
    event:           { type: Object, default: () => ({ code: 'EVT' }) },
});

const emit = defineEmits(['open-request']);

// Local writable copy so a bulk assign can patch rows optimistically
const itemsList = ref([...props.requestLines]);
watch(() => props.requestLines, v => { itemsList.value = [...v]; });

// Scoped to the active event first, same as Requests/Approvals/Change Orders.
const eventItems = computed(() =>
    props.event?.id ? itemsList.value.filter(i => i.eventId === props.event.id) : itemsList.value
);
const eventServiceOptions = computed(() =>
    props.event?.id ? props.serviceOptions.filter(o => o.eventId === props.event.id) : props.serviceOptions
);
// Venue filter is scoped to whichever venues are attached to the active
// event; an event with none attached falls back to every venue.
const eventVenues = computed(() =>
    props.event?.venueIds?.length ? props.venues.filter(v => props.event.venueIds.includes(v.id)) : props.venues
);
const eventAreas = computed(() => props.event?.id ? props.areas.filter(a => a.eventId === props.event.id) : props.areas);
const eventSpaces = computed(() => props.event?.id ? props.spaces.filter(s => s.eventId === props.event.id) : props.spaces);

// ── Filters ───────────────────────────────────────────────────────────────────
const q          = ref('');
const fDomain    = ref('all');
const fVenue     = ref('all');
const fFA        = ref('all');
const fOwner     = ref('all');
const fSpace     = ref('all');
const fArea      = ref('all');
const fGroup     = ref('all');
const fSub       = ref('all');
const moveInFrom  = ref('');
const moveInTo    = ref('');
const moveOutFrom = ref('');
const moveOutTo   = ref('');

function clearFilters() {
    q.value = ''; fDomain.value = 'all'; fVenue.value = 'all'; fFA.value = 'all';
    fOwner.value = 'all'; fSpace.value = 'all'; fArea.value = 'all';
    fGroup.value = 'all'; fSub.value = 'all';
    moveInFrom.value = ''; moveInTo.value = ''; moveOutFrom.value = ''; moveOutTo.value = '';
}
// Group/Subgroup are free-text on the catalog item, not a lookup table, so the
// filter's options are derived from whatever values are actually in use.
const uniqueGroups = computed(() => [...new Set(eventItems.value.map(i => i.group).filter(Boolean))].sort());
const uniqueSubgroups = computed(() => [...new Set(eventItems.value.map(i => i.sub).filter(Boolean))].sort());

// One chip per active (non-default) filter, shown below the toolbar; each
// knows how to clear just itself. Search stays a plain visible input, not a chip.
const activeFilterChips = computed(() => {
    const chips = [];
    if (fDomain.value !== 'all') chips.push({ key: 'domain', label: `Domain: ${domainOf(fDomain.value)?.label ?? fDomain.value}`, remove: () => { fDomain.value = 'all'; } });
    if (fVenue.value !== 'all') chips.push({ key: 'venue', label: `Venue: ${venueOf(fVenue.value)?.name ?? fVenue.value}`, remove: () => { fVenue.value = 'all'; } });
    if (fFA.value !== 'all') chips.push({ key: 'fa', label: `Functional Area: ${fFA.value}`, remove: () => { fFA.value = 'all'; } });
    if (fOwner.value !== 'all') chips.push({ key: 'owner', label: `Owner: ${personOf(fOwner.value)?.name ?? fOwner.value}`, remove: () => { fOwner.value = 'all'; } });
    if (fArea.value !== 'all') chips.push({ key: 'area', label: `Area: ${eventAreas.value.find(a => a.id === fArea.value)?.label ?? fArea.value}`, remove: () => { fArea.value = 'all'; } });
    if (fSpace.value !== 'all') chips.push({ key: 'space', label: `Space: ${eventSpaces.value.find(s => s.id === fSpace.value)?.name ?? fSpace.value}`, remove: () => { fSpace.value = 'all'; } });
    if (fGroup.value !== 'all') chips.push({ key: 'group', label: `Group: ${fGroup.value}`, remove: () => { fGroup.value = 'all'; } });
    if (fSub.value !== 'all') chips.push({ key: 'sub', label: `Subgroup: ${fSub.value}`, remove: () => { fSub.value = 'all'; } });
    if (moveInFrom.value || moveInTo.value) chips.push({ key: 'movein', label: `Move-in: ${moveInFrom.value || '…'} → ${moveInTo.value || '…'}`, remove: () => { moveInFrom.value = ''; moveInTo.value = ''; } });
    if (moveOutFrom.value || moveOutTo.value) chips.push({ key: 'moveout', label: `Move-out: ${moveOutFrom.value || '…'} → ${moveOutTo.value || '…'}`, remove: () => { moveOutFrom.value = ''; moveOutTo.value = ''; } });
    return chips;
});

function inDateRange(dateStr, from, to) {
    if (!from && !to) return true;
    if (!dateStr) return false;
    if (from && dateStr < from) return false;
    if (to && dateStr > to) return false;
    return true;
}

const filteredItems = computed(() => {
    let rows = eventItems.value.slice();
    if (fDomain.value !== 'all') rows = rows.filter(i => i.domain === fDomain.value);
    if (fVenue.value  !== 'all') rows = rows.filter(i => i.venue  === fVenue.value);
    if (fFA.value     !== 'all') rows = rows.filter(i => i.functionalArea === fFA.value);
    if (fOwner.value  !== 'all') rows = rows.filter(i => i.ownerId === fOwner.value);
    if (fSpace.value  !== 'all') rows = rows.filter(i => i.space === fSpace.value);
    if (fArea.value   !== 'all') rows = rows.filter(i => i.area === fArea.value);
    if (fGroup.value  !== 'all') rows = rows.filter(i => i.group === fGroup.value);
    if (fSub.value    !== 'all') rows = rows.filter(i => i.sub === fSub.value);
    if (moveInFrom.value || moveInTo.value)   rows = rows.filter(i => inDateRange(i.moveIn, moveInFrom.value, moveInTo.value));
    if (moveOutFrom.value || moveOutTo.value) rows = rows.filter(i => inDateRange(i.moveOut, moveOutFrom.value, moveOutTo.value));
    if (q.value) {
        const k = q.value.toLowerCase();
        rows = rows.filter(i =>
            (i.name || '').toLowerCase().includes(k) || (i.sku || '').toLowerCase().includes(k) ||
            i.requestId.toLowerCase().includes(k) || (i.requestTitle || '').toLowerCase().includes(k)
        );
    }
    return rows;
});

// ── Helpers ───────────────────────────────────────────────────────────────────
function domainOf(code)  { return props.domains.find(d => d.code === code); }
function venueOf(code)   { return props.venues.find(v => v.code === code); }
function faOf(code)      { return props.functionalAreas.find(f => f.code === code); }
function personOf(id)    { return props.people.find(p => p.id === id); }
function fmtMoney(n)     { return '$' + Number(n || 0).toLocaleString('en-US'); }
function fmtDate(s)      { return s ? new Date(s + 'T00:00:00').toLocaleDateString('en-US', { month: 'short', day: 'numeric' }) : '—'; }

const avatarColors = ['#7c2d12', '#0f766e', '#b45309', '#1d4ed8', '#6b21a8', '#155e75', '#854d0e'];
function avatarColor(initials) {
    if (!initials) return '#a39d96';
    const h = (initials.charCodeAt(0) + (initials.charCodeAt(1) || 0)) % avatarColors.length;
    return avatarColors[h];
}

// ── Selection ─────────────────────────────────────────────────────────────────
const selectedIds = ref(new Set());
watch(filteredItems, () => {
    // Drop any selections that fell out of the current filter set.
    const visible = new Set(filteredItems.value.map(i => i.id));
    selectedIds.value = new Set([...selectedIds.value].filter(id => visible.has(id)));
});

function toggleRow(id) {
    const next = new Set(selectedIds.value);
    next.has(id) ? next.delete(id) : next.add(id);
    selectedIds.value = next;
}
function toggleSelectAll() {
    selectedIds.value = allSelected.value ? new Set() : new Set(filteredItems.value.map(i => i.id));
}
const allSelected  = computed(() => filteredItems.value.length > 0 && filteredItems.value.every(i => selectedIds.value.has(i.id)));
const someSelected = computed(() => selectedIds.value.size > 0 && !allSelected.value);

// Unique subgroups across the selected rows — lets the assign modal narrow
// its bundle list to ones classified under the same subgroup.
const selectedSubGroups = computed(() => [...new Set(
    itemsList.value.filter(i => selectedIds.value.has(i.id)).map(i => i.sub).filter(Boolean)
)]);

// ── Bulk assign service option ──────────────────────────────────────────────
const showBulkAssign = ref(false);
const bulkSaving = ref(false);
const bulkError = ref(null);

function openBulkAssign() { bulkError.value = null; showBulkAssign.value = true; }
function closeBulkAssign() { showBulkAssign.value = false; }

async function bulkAssign(serviceOptionId) {
    bulkSaving.value = true;
    bulkError.value = null;
    try {
        const { data } = await axios.put(route('mp.request-lines.bulk-assign'), {
            line_ids: [...selectedIds.value],
            service_option_id: serviceOptionId,
        });
        const byId = new Map(data.lines.map(l => [l.id, l]));
        itemsList.value = itemsList.value.map(i => byId.has(i.id) ? { ...i, ...byId.get(i.id) } : i);
        selectedIds.value = new Set();
        closeBulkAssign();
    } catch (e) {
        bulkError.value = e.response?.status === 403
            ? "You don't have permission to assign one or more of the selected items."
            : 'Could not assign this service option to the selected items.';
    } finally {
        bulkSaving.value = false;
    }
}
</script>

<template>
    <div class="mp-page">
        <div class="mp-page-head">
            <div>
                <h1 class="mp-page-title">Items</h1>
                <p class="mp-page-sub">{{ eventItems.length }} request items for this event · select one or more to assign a service option</p>
            </div>
        </div>

        <!-- Toolbar -->
        <div class="itm-toolbar">
            <div class="itm-fb-search">
                <i class="bx bx-search"></i>
                <input v-model="q" placeholder="Find by item, SKU, request…"/>
            </div>
            <FilterPanel :active-filters="activeFilterChips" @clear-all="clearFilters">
                <div class="fp-section">
                    <div class="fp-section-title">Location</div>
                    <div class="fp-field">
                        <label>Domain</label>
                        <select v-model="fDomain">
                            <option value="all">All</option>
                            <option v-for="d in domains" :key="d.id" :value="d.code">{{ d.label }}</option>
                        </select>
                    </div>
                    <div class="fp-field">
                        <label>Venue</label>
                        <select v-model="fVenue">
                            <option value="all">All venues</option>
                            <option v-for="v in eventVenues" :key="v.code" :value="v.code">{{ v.name }}</option>
                        </select>
                    </div>
                    <div class="fp-field">
                        <label>Area</label>
                        <select v-model="fArea">
                            <option value="all">All</option>
                            <option v-for="a in eventAreas" :key="a.id" :value="a.id">{{ a.label }}</option>
                        </select>
                    </div>
                    <div class="fp-field">
                        <label>Space</label>
                        <select v-model="fSpace">
                            <option value="all">All</option>
                            <option v-for="s in eventSpaces" :key="s.id" :value="s.id">{{ s.name }}</option>
                        </select>
                    </div>
                </div>

                <div class="fp-section">
                    <div class="fp-section-title">Classification</div>
                    <div class="fp-field">
                        <label>Functional Area</label>
                        <select v-model="fFA">
                            <option value="all">All</option>
                            <option v-for="fa in functionalAreas" :key="fa.id" :value="fa.code">{{ fa.code }}</option>
                        </select>
                    </div>
                    <div class="fp-field">
                        <label>Group</label>
                        <select v-model="fGroup">
                            <option value="all">All</option>
                            <option v-for="g in uniqueGroups" :key="g" :value="g">{{ g }}</option>
                        </select>
                    </div>
                    <div class="fp-field">
                        <label>Subgroup</label>
                        <select v-model="fSub">
                            <option value="all">All</option>
                            <option v-for="s in uniqueSubgroups" :key="s" :value="s">{{ s }}</option>
                        </select>
                    </div>
                </div>

                <div class="fp-section">
                    <div class="fp-section-title">People &amp; Dates</div>
                    <div class="fp-field">
                        <label>Owner</label>
                        <select v-model="fOwner">
                            <option value="all">Anyone</option>
                            <option v-for="p in people" :key="p.id" :value="p.id">{{ p.name }}</option>
                        </select>
                    </div>
                    <div class="fp-field fp-field-range">
                        <label>Move-in</label>
                        <div class="fp-range">
                            <input type="date" v-model="moveInFrom"/>
                            <span>→</span>
                            <input type="date" v-model="moveInTo"/>
                        </div>
                    </div>
                    <div class="fp-field fp-field-range">
                        <label>Move-out</label>
                        <div class="fp-range">
                            <input type="date" v-model="moveOutFrom"/>
                            <span>→</span>
                            <input type="date" v-model="moveOutTo"/>
                        </div>
                    </div>
                </div>
            </FilterPanel>
        </div>

        <div v-if="activeFilterChips.length" class="itm-chips">
            <span v-for="f in activeFilterChips" :key="f.key" class="itm-chip">
                {{ f.label }}
                <button type="button" class="itm-chip-x" @click="f.remove()" aria-label="Remove filter">
                    <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18"/></svg>
                </button>
            </span>
        </div>

        <!-- Bulk actions -->
        <div v-if="selectedIds.size > 0" class="mp-bulkbar">
            <span class="mp-bulkbar-n">{{ selectedIds.size }} selected</span>
            <button class="mp-btn mp-btn-sm" @click="selectedIds = new Set()">Clear</button>
            <button class="mp-btn mp-btn-primary mp-btn-sm" @click="openBulkAssign">
                <i class="bx bx-purchase-tag"></i> Assign service option…
            </button>
        </div>

        <!-- Table -->
        <div class="mp-card mp-card-flush">
            <table class="mp-dt">
                <thead>
                    <tr>
                        <th><input type="checkbox" :checked="allSelected" :indeterminate="someSelected" @change="toggleSelectAll"/></th>
                        <th>Request</th>
                        <th>Item</th>
                        <th>Domain · Group</th>
                        <th>Venue</th>
                        <th>Site</th>
                        <th>Functional Area</th>
                        <th>Owner</th>
                        <th>Move-in → out</th>
                        <th class="ta-c">Qty</th>
                        <th class="ta-r">Value</th>
                        <th>Service option</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="i in filteredItems" :key="i.id" class="itm-row" :class="{ 'itm-row-on': selectedIds.has(i.id) }">
                        <td @click.stop><input type="checkbox" :checked="selectedIds.has(i.id)" @change="toggleRow(i.id)"/></td>
                        <td class="mono itm-req">{{ i.requestId }}</td>
                        <td>
                            <div class="itm-name">{{ i.name }}</div>
                            <div class="itm-sku mono">{{ i.sku }}</div>
                        </td>
                        <td>
                            <div class="itm-domgrp">
                                <span v-if="i.domain" class="mp-dtag" :style="{ background: domainOf(i.domain)?.chip, color: domainOf(i.domain)?.color }">
                                    <b>{{ i.domain }}</b>
                                </span>
                                <span v-else class="mp-muted">—</span>
                                <div v-if="i.group" class="itm-domgrp-txt">
                                    <div>{{ i.group }}</div>
                                    <div v-if="i.sub" class="itm-sub">{{ i.sub }}</div>
                                </div>
                            </div>
                        </td>
                        <td>{{ venueOf(i.venue)?.name ?? '—' }}</td>
                        <td>
                            <div v-if="i.spaceLabel || i.areaLabel">{{ i.spaceLabel || '—' }}</div>
                            <div v-if="i.areaLabel" class="itm-sub">{{ i.areaLabel }}</div>
                            <span v-if="!i.spaceLabel && !i.areaLabel" class="mp-muted">—</span>
                        </td>
                        <td>
                            <span v-if="i.functionalArea" class="mp-fa-tag">{{ i.functionalArea }}</span>
                            <span v-else class="mp-muted">—</span>
                        </td>
                        <td>
                            <span v-if="personOf(i.ownerId)" class="mp-avatar mp-avatar-sm" :style="{ background: avatarColor(i.ownerInitials) }">{{ i.ownerInitials }}</span>
                            <span v-else class="mp-muted">—</span>
                        </td>
                        <td class="itm-dates">
                            <span v-if="i.moveIn || i.moveOut">{{ fmtDate(i.moveIn) }} → {{ fmtDate(i.moveOut) }}</span>
                            <span v-else class="mp-muted">—</span>
                        </td>
                        <td class="ta-c mono">{{ i.qty }} {{ i.unit }}</td>
                        <td class="ta-r mono">{{ fmtMoney(i.value) }}</td>
                        <td>
                            <div v-if="i.serviceOptionName" class="itm-opt">
                                <div>{{ i.serviceOptionName }}</div>
                                <div class="itm-sub">{{ i.supplierName }}</div>
                            </div>
                            <span v-else class="mp-muted">Unassigned</span>
                        </td>
                        <td class="ta-r">
                            <button class="mp-icon-btn" title="Open request" @click="emit('open-request', i.requestId)"><i class="bx bx-link-external"></i></button>
                        </td>
                    </tr>
                    <tr v-if="!filteredItems.length">
                        <td colspan="13" class="itm-empty">No items match these filters.</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="mp-dt-foot">Showing <b>{{ filteredItems.length }}</b> of {{ eventItems.length }} items</div>
    </div>

    <BulkAssignServiceOptionModal
        v-if="showBulkAssign"
        :count="selectedIds.size"
        :item-sub-groups="selectedSubGroups"
        :service-options="eventServiceOptions"
        :suppliers="suppliers"
        :saving="bulkSaving"
        :error="bulkError"
        @close="closeBulkAssign"
        @assign="bulkAssign"
    />
</template>

<style scoped>
.mp-page { max-width: 100%; }
.mp-page-head {
    display: flex; justify-content: space-between; align-items: flex-end;
    gap: 24px; margin-bottom: 20px;
}
.mp-page-title { font-size: 24px; font-weight: 600; letter-spacing: -.02em; color: #1a1614; margin: 0; }
.mp-page-sub { font-size: 13px; color: #76706a; margin: 4px 0 0; }

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
.mp-btn-sm { padding: 4px 10px; font-size: 12px; }

.itm-toolbar { display: flex; align-items: center; gap: 8px; margin-bottom: 10px; }
.itm-fb-search {
    display: flex; align-items: center; gap: 8px;
    background: #fff; border: 1px solid #e8e4db; border-radius: 6px;
    padding: 6px 10px; color: #76706a; flex: 1;
}
.itm-fb-search input { border: none; outline: none; font-size: 12.5px; background: transparent; flex: 1; color: #1a1614; }

/* ── Filter panel sections (rendered inside <FilterPanel>'s slot) ───────────── */
.fp-section { padding: 14px 0; border-bottom: 1px solid #efece4; }
.fp-section:first-child { padding-top: 0; }
.fp-section:last-child { padding-bottom: 0; border-bottom: none; }
.fp-section-title { font-size: 11px; color: #76706a; text-transform: uppercase; letter-spacing: .06em; font-weight: 600; margin-bottom: 10px; }
.fp-field { display: flex; flex-direction: column; gap: 4px; margin-bottom: 10px; }
.fp-field:last-child { margin-bottom: 0; }
.fp-field label { font-size: 10.5px; color: #76706a; text-transform: uppercase; letter-spacing: .06em; }
.fp-field select, .fp-field input[type="date"] {
    appearance: none;
    background: #fff; border: 1px solid #e8e4db; border-radius: 6px;
    padding: 7px 10px; font-size: 12.5px; color: #1a1614; cursor: pointer; outline: none;
}
.fp-field select {
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24'%3E%3Cpath fill='none' stroke='%2376706a' stroke-width='2' stroke-linecap='round' stroke-linejoin='round' d='M6 9l6 6 6-6'/%3E%3C/svg%3E");
    background-repeat: no-repeat; background-position: right 8px center; padding-right: 24px;
}
.fp-range { display: flex; align-items: center; gap: 6px; }
.fp-range input { flex: 1; min-width: 0; }
.fp-range span { color: #a39d96; font-size: 12px; }

/* ── Active filter chips ──────────────────────────────────────────────────── */
.itm-chips { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 12px; }
.itm-chip {
    display: inline-flex; align-items: center; gap: 6px;
    font-size: 12px; font-weight: 500; color: #3d3833; background: #efece4;
    padding: 4px 6px 4px 10px; border-radius: 20px;
}
.itm-chip-x {
    width: 15px; height: 15px; border-radius: 50%; border: none; background: rgba(61,56,51,.12);
    color: #3d3833; display: inline-flex; align-items: center; justify-content: center; cursor: pointer;
}
.itm-chip-x:hover { background: rgba(61,56,51,.25); }

.mp-bulkbar {
    display: flex; align-items: center; gap: 10px;
    background: #fbfaf6; border: 1px solid #e8e4db; border-radius: 8px;
    padding: 8px 14px; margin-bottom: 12px;
}
.mp-bulkbar-n { font-size: 12.5px; font-weight: 600; color: #1a1614; margin-right: 4px; }

.mp-card { background: #fff; border: 1px solid #e8e4db; border-radius: 10px; padding: 16px 20px; margin-bottom: 14px; }
.mp-card-flush { padding: 0; overflow: hidden; }

.mp-dt { width: 100%; border-collapse: collapse; font-size: 12.5px; }
.mp-dt th {
    background: #fbfaf6; border-bottom: 1px solid #e8e4db;
    color: #76706a; font-size: 10.5px; text-transform: uppercase; letter-spacing: .05em;
    padding: 9px 12px; text-align: left; white-space: nowrap;
}
.mp-dt th.ta-c { text-align: center; }
.mp-dt th.ta-r { text-align: right; }
.mp-dt td { padding: 9px 12px; border-bottom: 1px solid #f3f0ea; vertical-align: middle; color: #1a1614; }
.itm-row { transition: background .12s; }
.itm-row:hover td { background: #fbfaf6; }
.itm-row-on td { background: rgba(15,118,110,.05); }

.itm-req { color: #76706a; font-size: 11.5px; }
.itm-name { font-weight: 500; }
.itm-sku { font-size: 11px; color: #76706a; margin-top: 1px; }
.itm-sub { font-size: 11px; color: #76706a; margin-top: 1px; }
.itm-domgrp { display: flex; align-items: center; gap: 9px; }
.itm-domgrp-txt { display: flex; flex-direction: column; justify-content: center; font-size: 12.5px; line-height: 1.35; }
.itm-domgrp-txt .itm-sub { margin-top: 0; }
.itm-dates { font-size: 12px; white-space: nowrap; }
.itm-opt { font-size: 12.5px; }
.itm-empty { text-align: center; padding: 20px; color: #76706a; font-size: 12.5px; }

.mp-dtag { display: inline-flex; align-items: center; gap: 4px; padding: 2px 8px; border-radius: 5px; font-size: 12px; font-weight: 600; }
.mp-fa-tag { font-size: 12px; color: #3d3833; background: #f6f5f1; padding: 2px 8px; border-radius: 5px; }
.mp-muted { color: #a39d96; font-size: 12px; }
.mp-avatar { display: inline-flex; align-items: center; justify-content: center; width: 24px; height: 24px; border-radius: 50%; color: #fff; font-size: 10px; font-weight: 700; flex-shrink: 0; }
.mp-avatar-sm { width: 22px; height: 22px; font-size: 9px; }

.mp-icon-btn { width: 26px; height: 26px; border-radius: 6px; border: 1px solid #e8e4db; background: #fff; color: #76706a; display: inline-flex; align-items: center; justify-content: center; cursor: pointer; font-size: 12px; transition: background .15s; }
.mp-icon-btn:hover { background: #fbfaf6; color: #1a1614; }

.mp-dt-foot { font-size: 12px; color: #76706a; text-align: right; margin-top: 8px; }

.mono { font-family: ui-monospace, 'SF Mono', Menlo, monospace; }
.ta-r { text-align: right; }
.ta-c { text-align: center; }
</style>
