<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';

const emit = defineEmits(['go-to']);

const props = defineProps({
    catalog: Array,
    domains: Array,
    event:   { type: Object, default: () => ({ code: 'EVT' }) },
});

// Local writable copy so we can optimistically add items
const catalogItems = ref([...props.catalog]);

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
const maxRate      = ref(20000);
const refreshing   = ref(false);

function refreshCatalog() {
    if (refreshing.value) return;
    refreshing.value = true;
    setTimeout(() => { refreshing.value = false; }, 700);
}

// ── Derived ───────────────────────────────────────────────────────────────────
const facets = computed(() =>
    props.domains.map(d => ({ ...d, count: catalogItems.value.filter(c => c.domain === d.id).length }))
);

const filteredCatalog = computed(() => {
    let items = catalogItems.value.slice();
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
function domainOf(id)  { return props.domains.find(d => d.id === id) || props.domains[0]; }
function fmtMoney(n)   { return '$' + Number(n).toLocaleString('en-US'); }
function stockPct(it)  { return Math.min(100, (it.stock / it.baseline) * 100); }
function stockColor(it) {
    const p = stockPct(it) / 100;
    return p > 0.8 ? '#166534' : p > 0.5 ? '#b45309' : '#991b1b';
}

// ── Add SKU modal ──────────────────────────────────────────────────────────
const DOMAIN_PREFIX = { IT:'IT', LOG:'LG', PWR:'PW', OVR:'OV', FFE:'FE' };
const UNIT_OPTS     = ['ea', 'm²', 'm', 'unit', 'kit', 'hr', 'day', 'set'];
const SOURCE_OPTS   = [{ id:'own', label:'Own pool' }, { id:'rental', label:'Rental' }, { id:'proc', label:'Procurement' }];

const showAddSku = ref(false);
const newRowSku  = ref(null);

function freshForm() {
    return { domain:'IT', group:'', sub:'', name:'', unit:'ea', rate:'', baseline:'', stock:'', source:'own', notes:'', skuOverride:null };
}
const skuForm = ref(freshForm());
const skuTail = ref('');

const skuGroupCode  = computed(() => (skuForm.value.group.replace(/[^A-Za-z]/g,'').slice(0,2)||'XX').toUpperCase());
const autoSku       = computed(() => `${DOMAIN_PREFIX[skuForm.value.domain]||skuForm.value.domain}-${skuGroupCode.value}-${skuTail.value}`);
const sku           = computed(() => skuForm.value.skuOverride ?? autoSku.value);
const skuValid      = computed(() => skuForm.value.name.trim() && skuForm.value.group.trim() && skuForm.value.rate && skuForm.value.baseline);
const skuBaselinePct = computed(() => {
    const b = +skuForm.value.baseline, s = +skuForm.value.stock;
    return b && s ? Math.min(100, Math.round((s / b) * 100)) : 0;
});
const skuCovColor = computed(() => skuBaselinePct.value >= 80 ? '#16a34a' : skuBaselinePct.value >= 50 ? '#b45309' : '#dc2626');

function openAddSku() {
    skuForm.value = freshForm();
    skuTail.value = String(100 + Math.floor(Math.random() * 8900)).padStart(4, '0');
    showAddSku.value = true;
}
function closeSkuModal() { showAddSku.value = false; }

function addToCatalog() {
    if (!skuValid.value) return;
    const item = {
        sku: sku.value, domain: skuForm.value.domain,
        group: skuForm.value.group.trim(), sub: skuForm.value.sub.trim(),
        name: skuForm.value.name.trim(), unit: skuForm.value.unit,
        rate: +skuForm.value.rate, baseline: +skuForm.value.baseline,
        stock: +skuForm.value.stock || 0,
    };
    catalogItems.value.unshift(item);
    newRowSku.value = item.sku;
    setTimeout(() => { newRowSku.value = null; }, 2400);
    catDomain.value = 'all';
    closeSkuModal();
}

function onEsc(e) { if (e.key === 'Escape' && showAddSku.value) closeSkuModal(); }
onMounted(()   => document.addEventListener('keydown', onEsc));
onUnmounted(() => document.removeEventListener('keydown', onEsc));
</script>

<template>
    <div class="mp-page cat-root">

        <!-- Page header -->
        <div class="mp-page-head">
            <div>
                <h1 class="mp-page-title">Item catalog</h1>
                <p class="mp-page-sub">{{ catalogItems.length }} active SKUs across {{ domains.length }} domains · baseline data from FWC22 Q-final</p>
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
                        <span class="mono">{{ catalog.length }}</span>
                    </button>
                    <button
                        v-for="d in facets" :key="d.id"
                        class="cat-facet"
                        :class="{ 'cat-facet-on': catDomain === d.id }"
                        @click="catDomain = d.id"
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
                    <input v-model="maxRate" type="range" min="0" max="20000" step="500" class="cat-range-slider" />
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
                            <tr v-for="it in filteredCatalog" :key="it.sku" class="cat-row" :class="{ 'cat-row-new': it.sku === newRowSku }">
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
                                <td><button class="mp-btn mp-btn-sm" @click.stop="emit('go-to', 'new', it)">+ Add</button></td>
                            </tr>
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

                <div class="dt-foot">Showing <b>{{ filteredCatalog.length }}</b> of {{ catalog.length }} items</div>
            </div>
        </div>
    </div>

    <!-- ── Add SKU modal ──────────────────────────────────────────────── -->
    <Teleport to="body">
        <div v-if="showAddSku" class="skum-scrim" @click.self="closeSkuModal">
            <div class="skum">

                <header class="skum-hd">
                    <div class="skum-hd-l">
                        <div class="skum-hd-tag">
                            <span class="mono">{{ event.code }}</span>
                            <span>·</span>
                            <span>Catalog</span>
                        </div>
                        <h2 class="skum-title">Add SKU</h2>
                        <p class="skum-sub">Register a new catalog item. Baseline drives stock coverage and procurement gap reports.</p>
                    </div>
                    <button class="skum-x" @click="closeSkuModal">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18"/></svg>
                    </button>
                </header>

                <div class="skum-body">

                    <!-- 1 · Classification -->
                    <section class="skum-sec">
                        <div class="skum-sec-h">
                            <span class="skum-sec-n mono">1</span>
                            <span class="skum-sec-t">Classification</span>
                            <span class="skum-sec-help">How this item is grouped in the catalog.</span>
                        </div>
                        <div class="field" style="margin-bottom:14px">
                            <label class="field-lbl">Domain</label>
                            <div class="skum-domains">
                                <button v-for="d in domains" :key="d.id" type="button"
                                    class="skum-dom" :class="{ 'skum-dom-on': skuForm.domain === d.id }"
                                    :style="skuForm.domain === d.id ? { borderColor: d.color, background: d.chip } : {}"
                                    @click="skuForm.domain = d.id">
                                    <span class="skum-dom-top">
                                        <span class="skum-dom-sw" :style="{ background: d.color }"></span>
                                        <span class="skum-dom-lbl">{{ d.label }}</span>
                                    </span>
                                    <span class="skum-dom-desc">{{ d.desc ?? d.label }}</span>
                                </button>
                            </div>
                        </div>
                        <div class="skum-grid skum-grid-2">
                            <div class="field">
                                <label class="field-lbl">Group</label>
                                <input v-model="skuForm.group" type="text" placeholder="e.g. Network, Tents, Generators" />
                            </div>
                            <div class="field">
                                <label class="field-lbl">Subgroup</label>
                                <input v-model="skuForm.sub" type="text" placeholder="e.g. Switches, Structures, Distro" />
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
                            <input v-model="skuForm.name" type="text" placeholder="e.g. Cisco Catalyst 9300-48P" />
                        </div>
                        <div class="skum-grid skum-grid-12">
                            <div class="field">
                                <label class="field-lbl">SKU code</label>
                                <div class="skum-skubox">
                                    <input class="mono" type="text" :value="sku"
                                        @input="skuForm.skuOverride = $event.target.value" />
                                    <button type="button" class="skum-skubtn" title="Regenerate from domain + group"
                                        @click="skuForm.skuOverride = null; skuTail = String(100 + Math.floor(Math.random() * 8900)).padStart(4,'0')">
                                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 1 1 3 6.7M3 21v-5h5"/></svg>
                                    </button>
                                </div>
                                <span class="field-hint">Auto-generated from domain + group. Edit if you have an existing supplier code.</span>
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
                                <label class="field-lbl">Rate (USD)</label>
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
                                <label class="field-lbl">Baseline ({{ event.code }} target)</label>
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

                </div><!-- /.skum-body -->

                <footer class="skum-ft">
                    <div class="skum-ft-l">
                        <span class="mono skum-ft-chip">{{ sku }}</span>
                        <span v-if="skuValid" class="skum-ft-ok">
                            <span class="skum-ft-dot" style="background:#16a34a"></span>Ready to add
                        </span>
                        <span v-else class="skum-ft-warn">
                            <span class="skum-ft-dot" style="background:#b45309"></span>Name, group, rate &amp; baseline required
                        </span>
                    </div>
                    <div class="skum-ft-r">
                        <button class="mp-btn" @click="closeSkuModal">Cancel</button>
                        <button class="mp-btn mp-btn-primary" :disabled="!skuValid" @click="addToCatalog">Add to catalog</button>
                    </div>
                </footer>

            </div>
        </div>
    </Teleport>
</template>

<style scoped>
/* ── Page shell ───────────────────────────────────────────────────────────── */
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
    font-size: 13px; color: #1a1614; cursor: pointer; transition: background .15s;
}
.mp-btn:hover { background: #f6f5f1; }
.mp-btn-primary { background: #0f766e; border-color: #0f766e; color: #fff; }
.mp-btn-primary:hover { background: #0d9488; }
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
.mp-card { background: #fff; border: 1px solid #e8e4db; border-radius: 10px; overflow: hidden; margin-bottom: 14px; }
.mp-card-flush { padding: 0; }
.mp-dt { width: 100%; border-collapse: collapse; font-size: 13px; }
.mp-dt th {
    background: #fbfaf6; border-bottom: 1px solid #e8e4db;
    color: #76706a; font-size: 11px; text-transform: uppercase; letter-spacing: .05em;
    padding: 10px 14px; text-align: left; white-space: nowrap;
}
.mp-dt td { padding: 11px 14px; border-bottom: 1px solid #f3f0ea; vertical-align: middle; color: #1a1614; }
.cat-row { cursor: default; }
.cat-row:last-child td { border-bottom: none; }
.cat-name { font-weight: 500; }
.cat-grp { font-size: 11px; color: #76706a; margin-top: 2px; }

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
@keyframes skum-fade { from { opacity:0 } to { opacity:1 } }
@keyframes skum-pop  { from { opacity:0; transform:translateY(14px) scale(.97) } to { opacity:1; transform:none } }
@keyframes skum-newrow { 0%,100%{background:transparent} 20%{background:rgba(15,118,110,.12)} }
.cat-row-new td { animation: skum-newrow 2.4s ease; }
.cat-tile-new   { animation: skum-newrow 2.4s ease; }

.skum-scrim {
    position: fixed; inset: 0; z-index: 1000;
    background: rgba(26,22,20,.45);
    display: flex; align-items: flex-start; justify-content: center;
    padding: 40px 16px; overflow-y: auto;
    animation: skum-fade .18s ease;
}
.skum {
    background: #fff; border: 1px solid #e8e4db; border-radius: 14px;
    width: 100%; max-width: 680px;
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
    cursor: pointer; color: #76706a; flex-shrink: 0; margin-left: 12px;
    transition: background .12s;
}
.skum-x:hover { background: #f6f5f1; }
.skum-body { padding: 0 24px; overflow-y: auto; max-height: 62vh; }
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
.skum-grid    { display: grid; gap: 14px; }
.skum-grid-2  { grid-template-columns: 1fr 1fr; }
.skum-grid-3  { grid-template-columns: 1fr 1fr 1fr; }
.skum-grid-12 { grid-template-columns: 1fr 1fr; }
.field { display: flex; flex-direction: column; gap: 5px; }
.field-lbl  { font-size: 11.5px; font-weight: 600; color: #3d3833; }
.field-hint { font-size: 11px; color: #76706a; line-height: 1.4; }
.field input, .field textarea {
    border: 1px solid #e8e4db; border-radius: 7px; padding: 8px 11px;
    font-size: 13px; color: #1a1614; background: #fff; outline: none;
    transition: border-color .12s;
}
.field input:focus, .field textarea:focus {
    border-color: #0f766e; box-shadow: 0 0 0 3px rgba(15,118,110,.1);
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
.skum-sel-wrap svg { position: absolute; right: 10px; pointer-events: none; color: #76706a; }
.skum-ft {
    display: flex; align-items: center; justify-content: space-between;
    gap: 12px; padding: 16px 24px;
    border-top: 1px solid #e8e4db;
    background: #fbfaf6; border-radius: 0 0 13px 13px;
}
.skum-ft-l { display: flex; align-items: center; gap: 10px; min-width: 0; }
.skum-ft-r { display: flex; gap: 8px; flex-shrink: 0; }
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
