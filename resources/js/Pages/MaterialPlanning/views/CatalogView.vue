<script setup>
import { ref, computed, watch } from 'vue';
import axios from 'axios';
import ConfirmModal from '../components/ConfirmModal.vue';
import ProgressButton from '../components/ProgressButton.vue';
import FormModal from '../components/FormModal.vue';

const emit = defineEmits(['go-to']);

const props = defineProps({
    catalog:        Array,
    domains:        Array,
    suppliers:      { type: Array, default: () => [] },
    serviceOptions: { type: Array, default: () => [] },
    permissions:    { type: Object, default: () => ({ isAdmin: false, managedDomain: null }) },
    event:          { type: Object, default: () => ({ code: 'EVT' }) },
});

function canManage(domain) { return props.permissions.isAdmin || props.permissions.managedDomain === domain; }

// Local writable copy so we can optimistically add items
const catalogItems = ref([...props.catalog]);

// Catalog items are now event-scoped — narrow to the active event, same
// pattern as eventItems/eventChangeOrders elsewhere in the module.
const eventCatalog = computed(() =>
    props.event?.id ? catalogItems.value.filter(i => i.eventId === props.event.id) : catalogItems.value
);
const eventServiceOptions = computed(() =>
    props.event?.id ? props.serviceOptions.filter(o => o.eventId === props.event.id) : props.serviceOptions
);
const eventDomains = computed(() =>
    props.event?.id ? props.domains.filter(d => d.eventId === props.event.id) : props.domains
);

// ── Filters ───────────────────────────────────────────────────────────────────
const catDomain  = ref('all');
const catSearch  = ref('');
const catSort    = ref('name');
const catView    = ref('list');
const availInStock     = ref(true);
const availTight       = ref(true);
const availOut         = ref(false);
const availProcurement = ref(false);
const sourceOwn        = ref(true);
const sourceRental     = ref(true);
const sourceNew        = ref(false);
// The slider's ceiling must track the actual catalog — a hardcoded cap silently
// hides any item priced above it with no way to reveal it (items > cap just vanish).
const rateCeiling = computed(() => eventCatalog.value.reduce((m, i) => Math.max(m, Math.ceil(i.rate / 1000) * 1000), 20000));
const maxRate      = ref(rateCeiling.value);
watch(rateCeiling, (next, prev) => {
    if (maxRate.value === prev) maxRate.value = next; // only auto-follow if the user hasn't narrowed it manually
});
const refreshing   = ref(false);

function refreshCatalog() {
    if (refreshing.value) return;
    refreshing.value = true;
    setTimeout(() => { refreshing.value = false; }, 700);
}

// ── Derived ───────────────────────────────────────────────────────────────────
const facets = computed(() =>
    eventDomains.value.map(d => ({ ...d, count: eventCatalog.value.filter(c => c.domain === d.code).length }))
);

const filteredCatalog = computed(() => {
    let items = eventCatalog.value.slice();
    if (catDomain.value !== 'all') items = items.filter(i => i.domain === catDomain.value);
    if (catSearch.value) {
        const q = catSearch.value.toLowerCase();
        items = items.filter(i => i.name.toLowerCase().includes(q) || i.sku.toLowerCase().includes(q));
    }
    items = items.filter(i => i.rate <= maxRate.value);

    const sortFns = {
        name:  (a, b) => a.name.localeCompare(b.name),
        sku:   (a, b) => a.sku.localeCompare(b.sku),
        rate:  (a, b) => a.rate - b.rate,
        stock: (a, b) => (b.stock / b.baseline) - (a.stock / a.baseline),
    };
    items.sort(sortFns[catSort.value] || sortFns.name);
    return items;
});

// ── Helpers ───────────────────────────────────────────────────────────────────
function domainOf(code) { return eventDomains.value.find(d => d.code === code) || eventDomains.value[0]; }
function fmtMoney(n)   { return '$' + Number(n).toLocaleString('en-US'); }
function stockPct(it)  { return Math.min(100, (it.stock / it.baseline) * 100); }
function stockColor(it) {
    const p = stockPct(it) / 100;
    return p > 0.8 ? '#166534' : p > 0.5 ? '#b45309' : '#991b1b';
}
function supplierOf(code) { return props.suppliers.find(s => s.code === code) || props.suppliers[props.suppliers.length - 1] || { name: code }; }

// ── Add SKU modal ──────────────────────────────────────────────────────────
const DOMAIN_PREFIX = { IT:'IT', LOG:'LG', PWR:'PW', OVR:'OV', FFE:'FE' };
const UNIT_OPTS     = ['ea', 'm²', 'm', 'unit', 'kit', 'hr', 'day', 'set'];
const SOURCE_OPTS   = [{ id:'own', label:'Own pool' }, { id:'rental', label:'Rental' }, { id:'proc', label:'Procurement' }];

const showAddSku = ref(false);
const newRowSku  = ref(null);
// Set when editing an existing SKU — the modal pre-fills, locks Domain + SKU
// code (neither is updatable server-side), and PUTs instead of POSTs.
const editTarget = ref(null);

function freshForm() {
    return { domain:'IT', group:'', sub:'', name:'', unit:'ea', rate:'', baseline:'', stock:'', source:'own', notes:'', skuOverride:null };
}
const skuForm = ref(freshForm());
const skuTail = ref('');

const skuGroupCode  = computed(() => (skuForm.value.group.replace(/[^A-Za-z]/g,'').slice(0,2)||'XX').toUpperCase());
const autoSku       = computed(() => `${DOMAIN_PREFIX[skuForm.value.domain]||skuForm.value.domain}-${skuGroupCode.value}-${skuTail.value}`);
const sku           = computed(() => skuForm.value.skuOverride ?? autoSku.value);
const skuValid      = computed(() =>
    skuForm.value.name.trim() && skuForm.value.group.trim() && skuForm.value.sub.trim() && sku.value.trim()
);
// Only surfaced once the user tries to save with something missing, so the
// modal doesn't open already covered in red.
const attemptedSave = ref(false);
const skuFieldErrors = computed(() => ({
    group: attemptedSave.value && !skuForm.value.group.trim(),
    sub: attemptedSave.value && !skuForm.value.sub.trim(),
    name: attemptedSave.value && !skuForm.value.name.trim(),
    sku: attemptedSave.value && !sku.value.trim(),
}));
// Group/Subgroup are picked from what's already used for the selected domain
// (across all events — a brand-new event's catalog starts empty, so scoping
// this to eventCatalog would leave the dropdown with nothing to offer).
const groupOptions = computed(() =>
    [...new Set(catalogItems.value.filter(c => c.domain === skuForm.value.domain).map(c => c.group))].filter(Boolean).sort()
);
const subOptions = computed(() =>
    [...new Set(catalogItems.value.filter(c => c.domain === skuForm.value.domain && c.group === skuForm.value.group).map(c => c.sub))].filter(Boolean).sort()
);

const skuBaselinePct = computed(() => {
    const b = +skuForm.value.baseline, s = +skuForm.value.stock;
    return b && s ? Math.min(100, Math.round((s / b) * 100)) : 0;
});
const skuCovColor = computed(() => skuBaselinePct.value >= 80 ? '#16a34a' : skuBaselinePct.value >= 50 ? '#b45309' : '#dc2626');

function openAddSku() {
    editTarget.value = null;
    skuForm.value = freshForm();
    skuTail.value = String(100 + Math.floor(Math.random() * 8900)).padStart(4, '0');
    attemptedSave.value = false;
    showAddSku.value = true;
}
function openEdit(it) {
    editTarget.value = it;
    skuForm.value = {
        domain: it.domain, group: it.group, sub: it.sub, name: it.name, unit: it.unit,
        rate: String(it.rate), baseline: String(it.baseline), stock: String(it.stock),
        source: 'own', notes: '', skuOverride: it.sku,
    };
    attemptedSave.value = false;
    showAddSku.value = true;
}
function closeSkuModal() { showAddSku.value = false; editTarget.value = null; }

const savingSku = ref(false);
const skuError = ref(null);

async function saveSku() {
    if (savingSku.value) return;
    if (!skuValid.value) { attemptedSave.value = true; return; }
    savingSku.value = true;
    skuError.value = null;
    try {
        const payload = {
            group: skuForm.value.group.trim(),
            sub: skuForm.value.sub.trim(),
            name: skuForm.value.name.trim(),
            unit: skuForm.value.unit,
            rate: +skuForm.value.rate,
            baseline: +skuForm.value.baseline,
            stock: +skuForm.value.stock || 0,
        };
        let data;
        if (editTarget.value) {
            ({ data } = await axios.put(route('mp.catalog-items.update', editTarget.value.dbId), payload));
            const idx = catalogItems.value.findIndex(i => i.sku === data.sku);
            if (idx !== -1) catalogItems.value[idx] = data;
        } else {
            ({ data } = await axios.post(route('mp.catalog-items.store'), {
                ...payload,
                sku: sku.value,
                domain_code: skuForm.value.domain,
                event_id: props.event.id,
            }));
            catalogItems.value.unshift(data);
        }
        newRowSku.value = data.sku;
        setTimeout(() => { newRowSku.value = null; }, 2400);
        catDomain.value = 'all';
        closeSkuModal();
    } catch (e) {
        skuError.value = e.response?.status === 403
            ? "You don't have permission to save this SKU."
            : (e.response?.data?.errors?.sku?.[0] ?? 'Could not save this SKU. Please try again.');
    } finally {
        savingSku.value = false;
    }
}

// ── Delete SKU ────────────────────────────────────────────────────────────
const confirmDeleteItem = ref(null);
const deletingItem = ref(false);
const deleteItemError = ref(null);
const deleteItemUsage = ref(null); // { requests: [...codes], changeOrders: [...codes] } when in use
function askDelete(it) { confirmDeleteItem.value = it; deleteItemError.value = null; deleteItemUsage.value = null; }
async function confirmDelete() {
    if (!confirmDeleteItem.value) return;
    deletingItem.value = true;
    deleteItemError.value = null;
    deleteItemUsage.value = null;
    try {
        await axios.delete(route('mp.catalog-items.destroy', confirmDeleteItem.value.dbId));
        catalogItems.value = catalogItems.value.filter(i => i.sku !== confirmDeleteItem.value.sku);
        confirmDeleteItem.value = null;
    } catch (e) {
        deleteItemError.value = e.response?.status === 403
            ? "You don't have permission to remove this SKU."
            : (e.response?.data?.message ?? 'Could not remove this SKU.');
        deleteItemUsage.value = e.response?.data?.usage ?? null;
    } finally {
        deletingItem.value = false;
    }
}

</script>

<template>
    <div class="mp-page cat-root">

        <!-- Page header -->
        <div class="mp-page-head">
            <div>
                <h1 class="mp-page-title">Item catalog</h1>
                <p class="mp-page-sub">{{ eventCatalog.length }} active SKUs across {{ eventDomains.length }} domains · {{ eventServiceOptions.length }} service option bundles in Service options</p>
            </div>
            <div class="mp-head-actions">
                <button class="mp-btn mp-btn-icon" title="Refresh catalog" @click="refreshCatalog">
                    <svg :class="{ 'spin': refreshing }" viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M23 4v6h-6"/><path d="M1 20v-6h6"/>
                        <path d="M3.51 9a9 9 0 0114.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0020.49 15"/>
                    </svg>
                </button>
                <button class="mp-btn">Export</button>
                <button class="mp-btn">Manage baselines</button>
                <button class="mp-btn mp-btn-primary" @click="openAddSku">+ Add SKU</button>
            </div>
        </div>

        <!-- 2-column layout -->
        <div class="cat-layout">

            <!-- ── Filter sidebar ────────────────────────────────────── -->
            <aside class="cat-side">
                <div class="cat-side-h">Filter</div>

                <!-- Domain facets -->
                <div class="cat-side-grp">
                    <div class="cat-side-lbl">Domain</div>
                    <button class="cat-facet" :class="{ 'cat-facet-on': catDomain === 'all' }" @click="catDomain = 'all'">
                        <span>All domains</span>
                        <span class="mono">{{ eventCatalog.length }}</span>
                    </button>
                    <button
                        v-for="d in facets" :key="d.id"
                        class="cat-facet"
                        :class="{ 'cat-facet-on': catDomain === d.code }"
                        @click="catDomain = d.code"
                    >
                        <span class="cat-facet-l">
                            <span class="cat-facet-sw" :style="{ background: d.color }"></span>
                            <span>{{ d.label }}</span>
                        </span>
                        <span class="mono">{{ d.count }}</span>
                    </button>
                </div>

                <!-- Availability -->
                <div class="cat-side-grp">
                    <div class="cat-side-lbl">Availability</div>
                    <label class="cat-check"><input v-model="availInStock" type="checkbox" /> In stock</label>
                    <label class="cat-check"><input v-model="availTight" type="checkbox" /> Tight stock</label>
                    <label class="cat-check"><input v-model="availOut" type="checkbox" /> Out of stock</label>
                    <label class="cat-check"><input v-model="availProcurement" type="checkbox" /> Procurement pending</label>
                </div>

                <!-- Rate range -->
                <div class="cat-side-grp">
                    <div class="cat-side-lbl">Rate range</div>
                    <input v-model="maxRate" type="range" min="0" :max="rateCeiling" step="500" class="cat-range-slider" />
                    <div class="cat-range-meta mono">$0 — {{ fmtMoney(maxRate) }}</div>
                </div>

                <!-- Source -->
                <div class="cat-side-grp">
                    <div class="cat-side-lbl">Source</div>
                    <label class="cat-check"><input v-model="sourceOwn" type="checkbox" /> Own pool</label>
                    <label class="cat-check"><input v-model="sourceRental" type="checkbox" /> Rental — Pref. vendor</label>
                    <label class="cat-check"><input v-model="sourceNew" type="checkbox" /> Procurement (new)</label>
                </div>
            </aside>

            <!-- ── Main content ──────────────────────────────────────── -->
            <div class="cat-main">

                <!-- Toolbar -->
                <div class="cat-toolbar">
                    <div class="cat-search">
                        <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5" stroke-linecap="round"/></svg>
                        <input v-model="catSearch" placeholder="Search catalog by name or SKU…" />
                    </div>
                    <div class="cat-sort">
                        <label>Sort</label>
                        <select v-model="catSort" class="cat-sort-sel">
                            <option value="name">Name</option>
                            <option value="sku">SKU</option>
                            <option value="rate">Rate (low → high)</option>
                            <option value="stock">Stock</option>
                        </select>
                    </div>
                    <div class="cat-viewtoggle">
                        <button :class="{ on: catView === 'list' }" @click="catView = 'list'">List</button>
                        <button :class="{ on: catView === 'tile' }" @click="catView = 'tile'">Tiles</button>
                    </div>
                </div>

                <!-- List view -->
                <div v-if="catView === 'list'" class="mp-card mp-card-flush">
                    <table class="mp-dt cat-table">
                        <thead>
                            <tr>
                                <th>Domain</th>
                                <th>SKU</th>
                                <th>Item</th>
                                <th class="ta-r">Rate</th>
                                <th>Unit</th>
                                <th>Stock vs baseline</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <template v-for="it in filteredCatalog" :key="it.sku">
                                <tr
                                    class="cat-row"
                                    :class="{ 'cat-row-new': it.sku === newRowSku }"
                                >
                                    <td>
                                        <span class="mp-dtag" :style="{ background: domainOf(it.domain).chip, color: domainOf(it.domain).color }">
                                            <b>{{ it.domain }}</b>
                                        </span>
                                    </td>
                                    <td class="mono">{{ it.sku }}</td>
                                    <td>
                                        <div class="cat-name">{{ it.name }}</div>
                                        <div class="cat-grp">{{ it.group }} · {{ it.sub }}</div>
                                    </td>
                                    <td class="ta-r mono">{{ fmtMoney(it.rate) }}</td>
                                    <td class="mono">{{ it.unit }}</td>
                                    <td>
                                        <div class="stockbar">
                                            <div class="stockbar-track">
                                                <div class="stockbar-fill"
                                                    :style="{ width: stockPct(it) + '%', background: stockColor(it) }"
                                                />
                                            </div>
                                            <div class="stockbar-meta mono">
                                                <span :style="{ color: stockColor(it) }">{{ it.stock }}</span>
                                                <span>/ {{ it.baseline }}</span>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="ta-r cat-row-actions">
                                        <button class="mp-btn mp-btn-sm" @click.stop="emit('go-to', 'new', it)">+ Add</button>
                                        <button v-if="canManage(it.domain)" class="mp-icon-btn mp-icon-edit" title="Edit" @click.stop="openEdit(it)"><i class="bx bx-pencil"></i></button>
                                        <button v-if="canManage(it.domain)" class="mp-icon-btn mp-icon-del" title="Delete" @click.stop="askDelete(it)"><i class="bx bx-trash"></i></button>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                <!-- Tile view -->
                <div v-else class="cat-grid">
                    <div v-for="it in filteredCatalog" :key="it.sku" class="cat-tile" :class="{ 'cat-tile-new': it.sku === newRowSku }">
                        <div class="cat-tile-img" :style="{ background: domainOf(it.domain).chip }">
                            <div class="cat-tile-img-stripe"></div>
                            <span class="cat-tile-img-lbl mono">{{ it.group }} · {{ it.sub }}</span>
                        </div>
                        <div class="cat-tile-body">
                            <div class="cat-tile-name">{{ it.name }}</div>
                            <div class="cat-tile-meta">
                                <span class="mono">{{ it.sku }}</span>
                                <span class="mp-dtag mp-dtag-mini" :style="{ background: domainOf(it.domain).chip, color: domainOf(it.domain).color }">{{ it.domain }}</span>
                            </div>
                            <div class="cat-tile-foot">
                                <span class="mono cat-tile-rate">{{ fmtMoney(it.rate) }}<span class="cat-tile-unit"> / {{ it.unit }}</span></span>
                                <div class="stockbar stockbar-compact">
                                    <div class="stockbar-track">
                                        <div class="stockbar-fill" :style="{ width: stockPct(it) + '%', background: stockColor(it) }" />
                                    </div>
                                    <span class="stockbar-meta mono"><span :style="{ color: stockColor(it) }">{{ it.stock }}</span>/{{ it.baseline }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="dt-foot">Showing <b>{{ filteredCatalog.length }}</b> of {{ eventCatalog.length }} items</div>
            </div>
        </div>
    </div>

    <!-- ── Add SKU modal ──────────────────────────────────────────────── -->
    <FormModal
        :show="showAddSku"
        :title="editTarget ? 'Edit SKU' : 'Add SKU'"
        :subtitle="editTarget ? 'Update this catalog item. Domain and SKU code can\'t be changed once created.' : 'Register a new catalog item. Baseline drives stock coverage and procurement gap reports.'"
        @close="closeSkuModal"
    >
        <template #eyebrow>
            <span class="mono">{{ event.code }}</span><span>·</span><span>Catalog</span>
        </template>

                    <!-- 1 · Classification -->
                    <section class="skum-sec">
                        <div class="skum-sec-h">
                            <span class="skum-sec-n mono">1</span>
                            <span class="skum-sec-t">Classification</span>
                            <span class="skum-sec-help">How this item is grouped in the catalog.</span>
                        </div>
                        <div class="field" style="margin-bottom:14px">
                            <label class="field-lbl">Domain</label>
                            <div v-if="!editTarget" class="skum-domains">
                                <button v-for="d in eventDomains" :key="d.id" type="button"
                                    class="skum-dom" :class="{ 'skum-dom-on': skuForm.domain === d.code }"
                                    :style="skuForm.domain === d.code ? { borderColor: d.color, background: d.chip } : {}"
                                    @click="skuForm.domain = d.code; skuForm.group = ''; skuForm.sub = '';">
                                    <span class="skum-dom-top">
                                        <span class="skum-dom-sw" :style="{ background: d.color }"></span>
                                        <span class="skum-dom-lbl">{{ d.label }}</span>
                                    </span>
                                    <span class="skum-dom-desc">{{ d.desc ?? d.label }}</span>
                                </button>
                            </div>
                            <div v-else class="skum-preview">
                                <span class="mp-dtag" :style="{ background: domainOf(skuForm.domain).chip, color: domainOf(skuForm.domain).color }">
                                    {{ domainOf(skuForm.domain).label }}
                                </span>
                                <span class="field-hint">Locked — a SKU can't change domain once created.</span>
                            </div>
                        </div>
                        <div class="skum-grid skum-grid-2">
                            <div class="field">
                                <label class="field-lbl">Group</label>
                                <div class="skum-sel-wrap" :class="{ 'field-bad': skuFieldErrors.group }">
                                    <select v-model="skuForm.group" @change="skuForm.sub = ''">
                                        <option value="" disabled>Select a group…</option>
                                        <option v-for="g in groupOptions" :key="g" :value="g">{{ g }}</option>
                                    </select>
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6"/></svg>
                                </div>
                                <span v-if="skuFieldErrors.group" class="field-err">Required</span>
                                <span v-else-if="!groupOptions.length" class="field-hint">No groups exist yet for this domain.</span>
                            </div>
                            <div class="field">
                                <label class="field-lbl">Subgroup</label>
                                <div class="skum-sel-wrap" :class="{ 'field-bad': skuFieldErrors.sub }">
                                    <select v-model="skuForm.sub" :disabled="!skuForm.group">
                                        <option value="" disabled>Select a subgroup…</option>
                                        <option v-for="s in subOptions" :key="s" :value="s">{{ s }}</option>
                                    </select>
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6"/></svg>
                                </div>
                                <span v-if="skuFieldErrors.sub" class="field-err">Required</span>
                                <span v-else-if="skuForm.group && !subOptions.length" class="field-hint">No subgroups exist yet for this group.</span>
                            </div>
                        </div>
                    </section>

                    <!-- 2 · Identity -->
                    <section class="skum-sec">
                        <div class="skum-sec-h">
                            <span class="skum-sec-n mono">2</span>
                            <span class="skum-sec-t">Identity</span>
                            <span class="skum-sec-help">Catalog-facing name and unique SKU code.</span>
                        </div>
                        <div class="field" style="margin-bottom:12px">
                            <label class="field-lbl">Item name</label>
                            <input v-model="skuForm.name" type="text" placeholder="e.g. Cisco Catalyst 9300-48P" :class="{ 'field-bad': skuFieldErrors.name }" />
                            <span v-if="skuFieldErrors.name" class="field-err">Required</span>
                        </div>
                        <div class="skum-grid skum-grid-12">
                            <div class="field">
                                <label class="field-lbl">SKU code</label>
                                <div class="skum-skubox" :class="{ 'field-bad': skuFieldErrors.sku }">
                                    <input class="mono" type="text" :value="sku" :disabled="!!editTarget"
                                        @input="skuForm.skuOverride = $event.target.value" />
                                    <button v-if="!editTarget" type="button" class="skum-skubtn" title="Regenerate from domain + group"
                                        @click="skuForm.skuOverride = null; skuTail = String(100 + Math.floor(Math.random() * 8900)).padStart(4,'0')">
                                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 1 1 3 6.7M3 21v-5h5"/></svg>
                                    </button>
                                </div>
                                <span v-if="skuFieldErrors.sku" class="field-err">Required</span>
                                <span v-else class="field-hint">{{ editTarget ? 'Locked — can\'t be changed once created.' : 'Auto-generated from domain + group. Edit if you have an existing supplier code.' }}</span>
                            </div>
                            <div class="field">
                                <label class="field-lbl">Catalog preview</label>
                                <div class="skum-preview">
                                    <span class="mp-dtag" :style="{ background: domainOf(skuForm.domain).chip, color: domainOf(skuForm.domain).color }">
                                        {{ skuForm.domain }}
                                    </span>
                                    <span class="skum-preview-name">{{ skuForm.name.trim() || 'Item name appears here' }}</span>
                                </div>
                                <span class="field-hint">{{ skuForm.group.trim() || '—' }} · {{ skuForm.sub.trim() || '—' }}</span>
                            </div>
                        </div>
                    </section>

                    <!-- 3 · Pricing & unit -->
                    <section class="skum-sec">
                        <div class="skum-sec-h">
                            <span class="skum-sec-n mono">3</span>
                            <span class="skum-sec-t">Pricing &amp; unit</span>
                            <span class="skum-sec-help">Charge-out rate for material planning and budgeting.</span>
                        </div>
                        <div class="skum-grid skum-grid-3">
                            <div class="field">
                                <label class="field-lbl">Unit</label>
                                <div class="skum-sel-wrap">
                                    <select v-model="skuForm.unit">
                                        <option v-for="u in UNIT_OPTS" :key="u" :value="u">{{ u }}</option>
                                    </select>
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6"/></svg>
                                </div>
                            </div>
                            <div class="field">
                                <label class="field-lbl">Rate (USD) <span class="field-opt">optional</span></label>
                                <div class="skum-money">
                                    <span class="skum-money-pre">$</span>
                                    <input class="mono" v-model="skuForm.rate" type="number" min="0" placeholder="0" />
                                    <span class="skum-money-post">/ {{ skuForm.unit }}</span>
                                </div>
                            </div>
                            <div class="field">
                                <label class="field-lbl">Source</label>
                                <div class="skum-sources">
                                    <label v-for="s in SOURCE_OPTS" :key="s.id"
                                        class="skum-src" :class="{ 'skum-src-on': skuForm.source === s.id }">
                                        <input type="radio" name="skum-src" :value="s.id" v-model="skuForm.source" />
                                        <span>{{ s.label }}</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </section>

                    <!-- 4 · Stock baseline -->
                    <section class="skum-sec">
                        <div class="skum-sec-h">
                            <span class="skum-sec-n mono">4</span>
                            <span class="skum-sec-t">Stock baseline</span>
                            <span class="skum-sec-help">Target quantity vs. what is already secured.</span>
                        </div>
                        <div class="skum-grid skum-grid-2">
                            <div class="field">
                                <label class="field-lbl">Baseline ({{ event.code }} target) <span class="field-opt">optional</span></label>
                                <input class="mono" v-model="skuForm.baseline" type="number" min="0" placeholder="0" />
                                <span class="field-hint">Quantity needed at event peak. Drives the procurement gap.</span>
                            </div>
                            <div class="field">
                                <label class="field-lbl">Available now</label>
                                <input class="mono" v-model="skuForm.stock" type="number" min="0" placeholder="0" />
                                <span class="field-hint">Already in pool or under signed contract.</span>
                            </div>
                        </div>
                        <div class="skum-cov">
                            <div class="skum-cov-head">
                                <span class="skum-cov-lbl">Coverage</span>
                                <span class="mono skum-cov-pct" :style="{ color: skuForm.baseline ? skuCovColor : '#76706a' }">
                                    {{ skuForm.baseline ? skuBaselinePct + '%' : '—' }}
                                </span>
                                <span class="mono skum-cov-r">
                                    {{ (+skuForm.stock || 0).toLocaleString() }}
                                    <span class="skum-cov-of">of</span>
                                    {{ (+skuForm.baseline || 0).toLocaleString() }} {{ skuForm.unit }}
                                </span>
                            </div>
                            <div class="skum-cov-bar">
                                <div class="skum-cov-fill"
                                    :style="{ width: skuForm.baseline ? Math.min(100, skuBaselinePct) + '%' : '0%', background: skuCovColor }" />
                                <div class="skum-cov-mark" title="80% comfort line" />
                            </div>
                            <div v-if="skuForm.baseline && skuBaselinePct < 80" class="skum-cov-note">
                                Gap of <b class="mono">{{ Math.max(0, (+skuForm.baseline) - (+skuForm.stock || 0)).toLocaleString() }} {{ skuForm.unit }}</b>
                                would route through {{ skuForm.source === 'proc' ? 'procurement (lead-time review)' : skuForm.source === 'rental' ? 'preferred rental vendors' : 'own pool transfers' }}.
                            </div>
                        </div>
                    </section>

                    <!-- 5 · Notes -->
                    <section class="skum-sec">
                        <div class="skum-sec-h">
                            <span class="skum-sec-n mono">5</span>
                            <span class="skum-sec-t">Notes</span>
                            <span class="skum-sec-opt">optional</span>
                        </div>
                        <div class="field">
                            <textarea v-model="skuForm.notes" placeholder="Constraints, vendor terms, lead-time, references…"></textarea>
                        </div>
                    </section>

        <template #footer-left>
            <span class="mono skum-ft-chip">{{ sku }}</span>
            <span v-if="skuError" class="skum-ft-warn">
                <span class="skum-ft-dot" style="background:#b45309"></span>{{ skuError }}
            </span>
            <span v-else-if="skuValid" class="skum-ft-ok">
                <span class="skum-ft-dot" style="background:#16a34a"></span>Ready to {{ editTarget ? 'save' : 'add' }}
            </span>
            <span v-else class="skum-ft-warn">
                <span class="skum-ft-dot" style="background:#b45309"></span>Name, group, subgroup &amp; SKU required
            </span>
        </template>
        <template #footer-actions>
            <ProgressButton
                variant="primary"
                :loading="savingSku"
                :text="editTarget ? 'Save changes' : 'Add to catalog'"
                :loading-text="editTarget ? 'Saving…' : 'Adding…'"
                @click="saveSku"
            />
        </template>
    </FormModal>

    <ConfirmModal
        v-if="confirmDeleteItem"
        :title="`Remove ${confirmDeleteItem.sku}?`"
        confirm-text="Remove"
        loading-text="Removing…"
        :loading="deletingItem"
        :confirm-disabled="!!deleteItemUsage"
        danger
        @cancel="confirmDeleteItem = null"
        @confirm="confirmDelete"
    >
        <div v-if="deleteItemUsage" class="cfm-usage">
            <div class="cfm-usage-head">
                <i class="bx bx-error-circle"></i>
                <span>{{ deleteItemError }}</span>
            </div>
            <div v-if="deleteItemUsage.requests?.length" class="cfm-usage-grp">
                <span class="cfm-usage-lbl">{{ deleteItemUsage.requests.length === 1 ? 'Request' : 'Requests' }} ({{ deleteItemUsage.requests.length }})</span>
                <div class="cfm-usage-chips">
                    <span v-for="code in deleteItemUsage.requests" :key="code" class="cfm-chip mono">{{ code }}</span>
                </div>
            </div>
            <div v-if="deleteItemUsage.changeOrders?.length" class="cfm-usage-grp">
                <span class="cfm-usage-lbl">{{ deleteItemUsage.changeOrders.length === 1 ? 'Change order' : 'Change orders' }} ({{ deleteItemUsage.changeOrders.length }})</span>
                <div class="cfm-usage-chips">
                    <span v-for="code in deleteItemUsage.changeOrders" :key="code" class="cfm-chip mono">{{ code }}</span>
                </div>
            </div>
            <p class="cfm-usage-hint">Remove it from these first, then try again.</p>
        </div>
        <p v-else-if="deleteItemError" class="cfm-err">{{ deleteItemError }}</p>
    </ConfirmModal>
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
.mp-btn-primary { background: #1a1614; border-color: #1a1614; color: #fff; }
.mp-btn-primary:hover { background: #0a0806; border-color: #0a0806; }
.mp-btn-sm { padding: 4px 10px; font-size: 12px; }
.mp-btn-icon { padding: 7px 9px; color: #76706a; }
.mp-btn-icon:hover { color: #1a1614; }

@keyframes spin { to { transform: rotate(360deg); } }
.spin { animation: spin .7s linear; }

/* ── 2-col layout ─────────────────────────────────────────────────────────── */
.cat-layout { display: grid; grid-template-columns: 240px 1fr; gap: 16px; }

/* ── Filter sidebar ───────────────────────────────────────────────────────── */
.cat-side {
    background: #fff; border: 1px solid #e8e4db;
    border-radius: 10px; padding: 14px;
    align-self: start; position: sticky; top: 0;
}
.cat-side-h { font-size: 11px; color: #76706a; text-transform: uppercase; letter-spacing: .06em; font-weight: 600; margin-bottom: 10px; }
.cat-side-grp { border-top: 1px solid #efece4; padding-top: 12px; margin-top: 12px; }
.cat-side-lbl { font-size: 11px; color: #76706a; text-transform: uppercase; letter-spacing: .06em; margin-bottom: 6px; font-weight: 600; }

.cat-facet {
    display: flex; align-items: center; justify-content: space-between;
    width: 100%; padding: 5px 8px;
    background: transparent; border: 0; border-radius: 5px;
    font-size: 12.5px; color: #3d3833; cursor: pointer; text-align: left;
    transition: background .12s;
}
.cat-facet:hover { background: #f6f5f1; }
.cat-facet-on { background: #1a1614 !important; color: #fff; }
.cat-facet-on .mono { opacity: .7; }
.cat-facet-l { display: flex; align-items: center; gap: 8px; }
.cat-facet-sw { width: 9px; height: 9px; border-radius: 2px; flex-shrink: 0; }
.cat-facet .mono { font-size: 11px; opacity: .6; }

.cat-check {
    display: flex; align-items: center; gap: 8px;
    padding: 4px 0; font-size: 12.5px; color: #3d3833; cursor: pointer;
}
.cat-check input { accent-color: #0f766e; }

.cat-range-slider { width: 100%; accent-color: #0f766e; }
.cat-range-meta { font-size: 11px; color: #76706a; margin-top: 4px; }

/* ── Toolbar ──────────────────────────────────────────────────────────────── */
.cat-main { min-width: 0; }
.cat-toolbar { display: flex; align-items: center; gap: 12px; margin-bottom: 12px; }

.cat-search {
    flex: 1; display: flex; align-items: center; gap: 8px;
    background: #fff; border: 1px solid #e8e4db; border-radius: 6px;
    padding: 7px 10px; color: #76706a;
}
.cat-search input { border: 0; outline: none; background: transparent; flex: 1; font-size: 13px; color: #1a1614; }

.cat-sort { display: flex; align-items: center; gap: 6px; white-space: nowrap; }
.cat-sort label { font-size: 11px; color: #76706a; }
.cat-sort-sel {
    border: 1px solid #e8e4db; border-radius: 6px; background: #fff;
    padding: 6px 10px; font-size: 12.5px; color: #1a1614; cursor: pointer; outline: none;
}

.cat-viewtoggle {
    display: flex; background: #fff; border: 1px solid #e8e4db; border-radius: 6px; padding: 2px;
}
.cat-viewtoggle button {
    background: transparent; border: 0; padding: 5px 12px;
    font-size: 12px; border-radius: 4px; cursor: pointer; color: #76706a;
}
.cat-viewtoggle button.on { background: #1a1614; color: #fff; }

/* ── List table ───────────────────────────────────────────────────────────── */
.mp-card {
    background: #fff; border: 1px solid #e8e4db; border-radius: 10px; overflow: hidden; margin-bottom: 14px;
    box-shadow: 0 1px 0 rgba(20,16,12,.03), 0 1px 2px rgba(20,16,12,.04);
}
.mp-card-flush { padding: 0; }
.mp-dt { width: 100%; border-collapse: collapse; font-size: 13px; }
.mp-dt th {
    background: #fbfaf6; border-bottom: 1px solid #e8e4db;
    color: #76706a; font-size: 11px; text-transform: uppercase; letter-spacing: .05em;
    padding: 10px 14px; text-align: left; white-space: nowrap;
}
.mp-dt td { padding: 11px 14px; border-bottom: 1px solid #f3f0ea; vertical-align: middle; color: #1a1614; }
.cat-row { transition: background .12s; }
.cat-row:hover { background: #fbfaf6; }
.cat-row:last-child td { border-bottom: none; }
.cat-name { font-weight: 500; }
.cat-grp { font-size: 11px; color: #76706a; margin-top: 2px; }

.cat-row-actions { display: flex; gap: 4px; justify-content: flex-end; white-space: nowrap; }
.mp-icon-btn { width: 28px; height: 28px; border-radius: 6px; border: 1px solid transparent; display: inline-flex; align-items: center; justify-content: center; cursor: pointer; font-size: 13px; transition: background .15s; }
.mp-icon-edit { background: #fff7e6; border-color: #fde7b0; color: #d97706; }
.mp-icon-edit:hover { background: #fef3c7; }
.mp-icon-del { background: #fff1f2; border-color: #fecdd3; color: #dc2626; }
.mp-icon-del:hover { background: #ffe4e6; }
.cfm-err { font-size: 12.5px; color: #991b1b; margin-top: 8px; }

/* ── Delete-blocked usage panel ──────────────────────────────────────────── */
.cfm-usage {
    margin-top: 12px; text-align: left;
    background: #fef2f2; border: 1px solid #fecaca; border-radius: 9px;
    padding: 12px 14px;
}
.cfm-usage-head {
    display: flex; align-items: flex-start; gap: 7px;
    font-size: 12.5px; font-weight: 600; color: #991b1b; line-height: 1.4;
}
.cfm-usage-head i { font-size: 15px; flex-shrink: 0; margin-top: 1px; }
.cfm-usage-grp { margin-top: 10px; }
.cfm-usage-lbl {
    display: block; font-size: 10.5px; font-weight: 600; color: #b91c1c;
    text-transform: uppercase; letter-spacing: .05em; margin-bottom: 6px;
}
.cfm-usage-chips { display: flex; flex-wrap: wrap; gap: 6px; }
.cfm-chip {
    display: inline-flex; align-items: center;
    font-size: 11.5px; font-weight: 500; color: #7f1d1d;
    background: #fff; border: 1px solid #fca5a5;
    padding: 3px 8px; border-radius: 5px;
}
.cfm-usage-hint { font-size: 11.5px; color: #9f5252; margin: 10px 0 0; line-height: 1.4; }

.mp-dtag {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 2px 8px; border-radius: 5px; font-size: 12px; font-weight: 600;
}
.mp-dtag-mini { padding: 1px 5px; font-size: 11px; }

/* ── Stock bar ────────────────────────────────────────────────────────────── */
.stockbar { display: flex; align-items: center; gap: 8px; min-width: 140px; }
.stockbar-track { flex: 1; height: 5px; background: #e8e4db; border-radius: 3px; overflow: hidden; max-width: 110px; }
.stockbar-fill { height: 100%; border-radius: 3px; }
.stockbar-meta { font-size: 11.5px; color: #76706a; display: flex; gap: 3px; white-space: nowrap; }
.stockbar-compact { min-width: 0; }

/* ── Tile grid ────────────────────────────────────────────────────────────── */
.cat-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 12px; margin-bottom: 14px; }
.cat-tile {
    background: #fff; border: 1px solid #e8e4db;
    border-radius: 10px; overflow: hidden; cursor: pointer; transition: box-shadow .12s;
}
.cat-tile:hover { box-shadow: 0 4px 18px rgba(0,0,0,.08); }
.cat-tile-img { height: 90px; position: relative; overflow: hidden; }
.cat-tile-img-stripe {
    position: absolute; inset: 0;
    background-image: repeating-linear-gradient(135deg, transparent 0 12px, rgba(255,255,255,.45) 12px 13px);
}
.cat-tile-img-lbl {
    position: absolute; bottom: 8px; left: 10px;
    font-size: 10.5px; color: rgba(0,0,0,.6);
    background: rgba(255,255,255,.7); padding: 2px 6px; border-radius: 3px;
}
.cat-tile-body { padding: 12px 14px 14px; display: flex; flex-direction: column; gap: 6px; }
.cat-tile-name { font-size: 13px; font-weight: 500; line-height: 1.3; min-height: 34px; color: #1a1614; }
.cat-tile-meta { display: flex; align-items: center; gap: 8px; font-size: 11px; color: #76706a; justify-content: space-between; }
.cat-tile-rate { font-size: 14px; font-weight: 600; color: #1a1614; }
.cat-tile-unit { font-weight: 400; font-size: 11px; color: #76706a; }
.cat-tile-foot { display: flex; align-items: center; justify-content: space-between; gap: 10px; margin-top: 4px; }

/* ── Footer ───────────────────────────────────────────────────────────────── */
.dt-foot { font-size: 12px; color: #76706a; text-align: right; margin-top: 8px; }

.mono { font-family: ui-monospace, 'SF Mono', Menlo, monospace; }
.ta-r  { text-align: right; }

/* ── Add SKU modal ──────────────────────────────────────────────────────────── */
@keyframes skum-newrow { 0%,100%{background:transparent} 20%{background:rgba(15,118,110,.12)} }
.cat-row-new td { animation: skum-newrow 2.4s ease; }
.cat-tile-new   { animation: skum-newrow 2.4s ease; }

.skum-sec { padding: 20px 0; border-bottom: 1px solid #efece4; }
.skum-sec:last-child { border-bottom: none; }
.skum-sec-h { display: flex; align-items: center; gap: 10px; margin-bottom: 14px; }
.skum-sec-n {
    width: 22px; height: 22px; border-radius: 50%;
    background: #1a1614; color: #fff; font-size: 11px;
    display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.skum-sec-t    { font-size: 13px; font-weight: 600; color: #1a1614; }
.skum-sec-help { font-size: 12px; color: #76706a; }
.skum-sec-opt  { font-size: 11px; color: #a39d96; background: #f6f5f1; padding: 1px 7px; border-radius: 10px; }
.field-opt     { font-size: 10px; font-weight: 400; color: #a39d96; text-transform: none; letter-spacing: 0; }
.skum-grid    { display: grid; gap: 14px; }
.skum-grid-2  { grid-template-columns: 1fr 1fr; }
.skum-grid-3  { grid-template-columns: 1fr 1fr 1fr; }
.skum-grid-12 { grid-template-columns: 1fr 1fr; }
.field { display: flex; flex-direction: column; gap: 5px; }
.field-lbl  { font-size: 11.5px; font-weight: 600; color: #3d3833; }
.field-hint { font-size: 11px; color: #76706a; line-height: 1.4; }
.field-err  { font-size: 11px; color: #b91c1c; font-weight: 500; line-height: 1.4; }
.field input, .field textarea {
    border: 1px solid #e8e4db; border-radius: 7px; padding: 8px 11px;
    font-size: 13px; color: #1a1614; background: #fff; outline: none;
    transition: border-color .12s;
}
.field input:focus, .field textarea:focus {
    border-color: #0f766e; box-shadow: 0 0 0 3px rgba(15,118,110,.1);
}
.field input.field-bad, .field textarea.field-bad,
.skum-money.field-bad, .skum-skubox.field-bad {
    border-color: #dc2626;
}
.field input.field-bad:focus, .field textarea.field-bad:focus {
    box-shadow: 0 0 0 3px rgba(220,38,38,.1);
}
.field textarea { resize: vertical; min-height: 72px; font-family: inherit; }
.skum-domains { display: flex; flex-wrap: wrap; gap: 7px; }
.skum-dom {
    display: flex; flex-direction: column; gap: 2px;
    border: 1.5px solid #e8e4db; border-radius: 8px;
    padding: 7px 11px; background: #fbfaf6; cursor: pointer; text-align: left;
    transition: border-color .12s, background .12s;
}
.skum-dom:hover { border-color: #c8c2b8; background: #f6f5f1; }
.skum-dom-on { border-color: #0f766e !important; }
.skum-dom-top { display: flex; align-items: center; gap: 6px; }
.skum-dom-sw  { width: 9px; height: 9px; border-radius: 2px; flex-shrink: 0; }
.skum-dom-lbl { font-size: 12px; font-weight: 600; color: #1a1614; }
.skum-dom-desc { font-size: 10.5px; color: #76706a; white-space: nowrap; }
.skum-skubox {
    display: flex; align-items: stretch;
    border: 1px solid #e8e4db; border-radius: 7px; overflow: hidden;
    background: #fff; transition: border-color .12s;
}
.skum-skubox:focus-within { border-color: #0f766e; box-shadow: 0 0 0 3px rgba(15,118,110,.1); }
.skum-skubox input {
    flex: 1; border: none; border-radius: 0; padding: 8px 10px;
    font-size: 13px; color: #1a1614; background: transparent; outline: none;
}
.skum-skubox input:disabled { color: #76706a; background: #fbfaf6; cursor: not-allowed; }
.skum-skubtn {
    border: none; border-left: 1px solid #e8e4db; padding: 0 10px;
    background: #fbfaf6; cursor: pointer; color: #76706a;
    display: inline-flex; align-items: center; transition: background .12s;
}
.skum-skubtn:hover { background: #f0ede6; color: #1a1614; }
.skum-preview {
    display: flex; align-items: center; gap: 8px;
    border: 1px solid #e8e4db; border-radius: 7px;
    padding: 8px 11px; background: #fbfaf6; min-height: 38px;
}
.skum-preview-name { font-size: 13px; color: #1a1614; }
.skum-money {
    display: flex; align-items: center;
    border: 1px solid #e8e4db; border-radius: 7px; overflow: hidden;
    background: #fff; transition: border-color .12s;
}
.skum-money:focus-within { border-color: #0f766e; box-shadow: 0 0 0 3px rgba(15,118,110,.1); }
.skum-money-pre, .skum-money-post {
    padding: 0 10px; background: #fbfaf6; color: #76706a;
    font-size: 12.5px; white-space: nowrap;
    display: flex; align-items: center; align-self: stretch;
}
.skum-money-pre  { border-right: 1px solid #e8e4db; }
.skum-money-post { border-left:  1px solid #e8e4db; }
.skum-money input {
    flex: 1; border: none; border-radius: 0; padding: 8px 10px;
    font-size: 13px; color: #1a1614; background: transparent; outline: none;
}
.skum-sources { display: flex; flex-direction: column; gap: 5px; }
.skum-src {
    display: flex; align-items: center; gap: 8px; padding: 7px 10px;
    border-radius: 6px; border: 1.5px solid #e8e4db; cursor: pointer;
    font-size: 12.5px; color: #3d3833; background: #fbfaf6;
    transition: border-color .12s, background .12s;
}
.skum-src input[type="radio"] { display: none; }
.skum-src-on { border-color: #0f766e; background: rgba(15,118,110,.07); color: #0f766e; }
.skum-cov { margin-top: 14px; }
.skum-cov-head { display: flex; align-items: center; gap: 8px; margin-bottom: 6px; font-size: 12px; }
.skum-cov-lbl { color: #76706a; flex: 1; }
.skum-cov-pct { font-weight: 700; font-size: 13px; }
.skum-cov-r   { color: #76706a; margin-left: auto; }
.skum-cov-of  { color: #a39d96; }
.skum-cov-bar { position: relative; height: 8px; background: #e8e4db; border-radius: 4px; overflow: visible; }
.skum-cov-fill { height: 100%; border-radius: 4px; transition: width .3s ease, background .3s ease; }
.skum-cov-mark {
    position: absolute; top: -3px; left: 80%;
    width: 2px; height: 14px; background: #1a1614;
    border-radius: 1px; opacity: .3;
}
.skum-cov-note {
    margin-top: 8px; font-size: 11.5px; color: #76706a;
    background: #fef9ec; border: 1px solid #fde7b0;
    border-radius: 6px; padding: 7px 10px;
}
.skum-sel-wrap { position: relative; display: flex; align-items: center; }
.skum-sel-wrap select {
    width: 100%; appearance: none; -webkit-appearance: none;
    border: 1px solid #e8e4db; border-radius: 7px; padding: 8px 30px 8px 11px;
    font-size: 13px; color: #1a1614; background: #fff; outline: none;
    transition: border-color .12s; cursor: pointer;
}
.skum-sel-wrap select:focus { border-color: #0f766e; box-shadow: 0 0 0 3px rgba(15,118,110,.1); }
.skum-sel-wrap select:disabled { background: #fbfaf6; color: #a39d96; cursor: not-allowed; }
.skum-sel-wrap svg { position: absolute; right: 10px; pointer-events: none; color: #76706a; }
.skum-sel-wrap.field-bad select { border-color: #dc2626; }
.skum-sel-wrap.field-bad select:focus { box-shadow: 0 0 0 3px rgba(220,38,38,.1); }
.skum-ft-chip {
    font-size: 12px; background: #efece4; padding: 3px 9px;
    border-radius: 5px; color: #3d3833;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 180px;
}
.skum-ft-ok, .skum-ft-warn { display: flex; align-items: center; gap: 5px; font-size: 12px; }
.skum-ft-ok   { color: #16a34a; }
.skum-ft-warn { color: #b45309; }
.skum-ft-dot  { width: 6px; height: 6px; border-radius: 50%; flex-shrink: 0; }
</style>
