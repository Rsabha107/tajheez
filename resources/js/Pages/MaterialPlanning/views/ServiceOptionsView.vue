<script setup>
import { ref, computed } from 'vue';
import axios from 'axios';
import NewBundleModal from '../components/NewBundleModal.vue';
import NewServiceOptionItemModal from '../components/NewServiceOptionItemModal.vue';
import ConfirmModal from '../components/ConfirmModal.vue';
import FilterPanel from '../components/FilterPanel.vue';
import RefreshButton from '../components/RefreshButton.vue';

const props = defineProps({
    suppliers:       Array,
    serviceOptions:  Array,
    serviceOptionItems: { type: Array, default: () => [] },
    classifications: { type: Array, default: () => [] },
    itemGroups:      { type: Array, default: () => [] },
    itemSubgroups:   { type: Array, default: () => [] },
    permissions:     { type: Object, default: () => ({ isAdmin: false, managedDomain: null }) },
    event:           { type: Object, default: () => ({ code: 'EVT' }) },
    refreshing:      { type: Boolean, default: false },
});

const emit = defineEmits(['refresh']);

const activeTab = ref('options'); // 'options' = reusable library, 'bundles' = groups of options

// Local writable copies so we can optimistically add/edit
const optionsList = ref([...props.serviceOptions]);
const itemsList = ref([...props.serviceOptionItems]);

// ── Helpers ───────────────────────────────────────────────────────────────────
function supplierOf(code) { return props.suppliers.find(s => s.code === code) || props.suppliers[props.suppliers.length - 1]; }
function fmtMoney(n)    { return '$' + Number(n).toLocaleString('en-US'); }

const STATUS_COLORS = {
    success: { bg: '#dcfce7', fg: '#166534' },
    secondary: { bg: '#efece4', fg: '#3d3833' },
    danger: { bg: '#fee2e2', fg: '#991b1b' },
    warning: { bg: '#fef3c7', fg: '#92400e' },
    info: { bg: '#dbeafe', fg: '#1e3a8a' },
    primary: { bg: '#dbeafe', fg: '#1e3a8a' },
};
function colorMeta(colorKey) { return STATUS_COLORS[colorKey] || STATUS_COLORS.secondary; }

// ── Bundles: filters + derived ──────────────────────────────────────────────
const fSupplier       = ref('all');
const fClassification = ref('all');
const fGroup           = ref('all');
const q               = ref('');

// Bundles are now event-scoped — narrow to the active event, same pattern as
// eventItems/eventChangeOrders elsewhere in the module.
const eventOptions = computed(() =>
    props.event?.id ? optionsList.value.filter(o => o.eventId === props.event.id) : optionsList.value
);
const eventItemGroups = computed(() =>
    props.event?.id ? props.itemGroups.filter(g => g.eventId === props.event.id) : props.itemGroups
);
const eventItemSubgroups = computed(() =>
    props.event?.id ? props.itemSubgroups.filter(s => s.eventId === props.event.id) : props.itemSubgroups
);

const all = computed(() => eventOptions.value.map(o => ({ ...o, services: o.services || [] })));

const rows = computed(() => {
    let r = all.value.slice();
    if (fSupplier.value !== 'all') r = r.filter(o => o.services.some(s => s.supplier === fSupplier.value));
    if (fClassification.value !== 'all') r = r.filter(o => o.classificationId === fClassification.value);
    if (fGroup.value !== 'all') r = r.filter(o => o.itemGroupId === fGroup.value);
    if (q.value) {
        const k = q.value.toLowerCase();
        r = r.filter(o =>
            o.name.toLowerCase().includes(k) ||
            o.services.some(s => s.name.toLowerCase().includes(k) || (supplierOf(s.supplier)?.name ?? '').toLowerCase().includes(k))
        );
    }
    r.sort((a, b) => (b.isDefault ? 1 : 0) - (a.isDefault ? 1 : 0) || a.name.localeCompare(b.name));
    return r;
});

const totalServices = computed(() => all.value.reduce((s, o) => s + o.services.length, 0));
const avgServicesPerBundle = computed(() => all.value.length ? Math.round((totalServices.value / all.value.length) * 10) / 10 : 0);
const avgLead = computed(() => {
    const leads = all.value.flatMap(o => o.services.map(s => s.lead));
    if (!leads.length) return 0;
    return Math.round(leads.reduce((s, l) => s + l, 0) / leads.length);
});
const suspendedCount = computed(() => all.value.filter(o => o.classificationName === 'Suspended').length);
const noContractCount = computed(() =>
    all.value.reduce((n, o) => n + o.services.filter(s =>
        s.contract === '—' && s.supplier !== 'SUP-OWN' && s.supplier !== 'SUP-ITX'
    ).length, 0)
);

function clearBundleFilters() { fSupplier.value = 'all'; fClassification.value = 'all'; fGroup.value = 'all'; q.value = ''; }
const bundleActiveFilterChips = computed(() => {
    const chips = [];
    if (fSupplier.value !== 'all') chips.push({ key: 'supplier', label: `Supplier: ${supplierOf(fSupplier.value)?.name ?? fSupplier.value}`, remove: () => { fSupplier.value = 'all'; } });
    if (fClassification.value !== 'all') chips.push({ key: 'classification', label: `Classification: ${props.classifications.find(c => c.id === fClassification.value)?.name ?? fClassification.value}`, remove: () => { fClassification.value = 'all'; } });
    if (fGroup.value !== 'all') chips.push({ key: 'group', label: `Group: ${eventItemGroups.value.find(g => g.id === fGroup.value)?.label ?? fGroup.value}`, remove: () => { fGroup.value = 'all'; } });
    return chips;
});

// ── Service options (reusable library): filters + derived ───────────────────
// The library is now event-scoped too, same as bundles — narrow to the active event.
const eventItemsList = computed(() =>
    props.event?.id ? itemsList.value.filter(i => i.eventId === props.event.id) : itemsList.value
);

const iSupplier = ref('all');
const iGroup    = ref('all');
const iq        = ref('');

const itemRows = computed(() => {
    let r = eventItemsList.value.slice();
    if (iSupplier.value !== 'all') r = r.filter(i => i.supplierCode === iSupplier.value);
    if (iGroup.value !== 'all') r = r.filter(i => i.itemGroupId === iGroup.value);
    if (iq.value) {
        const k = iq.value.toLowerCase();
        r = r.filter(i =>
            i.name.toLowerCase().includes(k) ||
            i.id.toLowerCase().includes(k) ||
            (i.supplierName ?? '').toLowerCase().includes(k)
        );
    }
    r.sort((a, b) => a.name.localeCompare(b.name));
    return r;
});

const usedItemCount = computed(() => eventItemsList.value.filter(i => i.usageCount > 0).length);

function clearItemFilters() { iSupplier.value = 'all'; iGroup.value = 'all'; iq.value = ''; }
const itemActiveFilterChips = computed(() => {
    const chips = [];
    if (iSupplier.value !== 'all') chips.push({ key: 'supplier', label: `Supplier: ${supplierOf(iSupplier.value)?.name ?? iSupplier.value}`, remove: () => { iSupplier.value = 'all'; } });
    if (iGroup.value !== 'all') chips.push({ key: 'group', label: `Group: ${eventItemGroups.value.find(g => g.id === iGroup.value)?.label ?? iGroup.value}`, remove: () => { iGroup.value = 'all'; } });
    return chips;
});

// ── New / edit bundle modal ──────────────────────────────────────────────────
const showAddBundle    = ref(false);
const editBundleTarget = ref(null);
const justAddedBundle  = ref(null);

function openAddBundle()   { editBundleTarget.value = null; showAddBundle.value = true; }
function openEditBundle(o) { editBundleTarget.value = o; showAddBundle.value = true; }
function closeBundleModal() { showAddBundle.value = false; editBundleTarget.value = null; }

function onBundleSaved(option) {
    const idx = optionsList.value.findIndex(o => o.dbId === option.dbId);
    if (idx !== -1) optionsList.value[idx] = option;
    else optionsList.value.unshift(option);
    justAddedBundle.value = option.id;
    clearBundleFilters();
    closeBundleModal();
    setTimeout(() => { justAddedBundle.value = null; }, 2600);
}

// ── New / edit service option (library item) modal ──────────────────────────
const showAddItem    = ref(false);
const editItemTarget = ref(null);
const justAddedItem  = ref(null);

function openAddItem()   { editItemTarget.value = null; showAddItem.value = true; }
function openEditItem(i) { editItemTarget.value = i; showAddItem.value = true; }
function closeItemModal() { showAddItem.value = false; editItemTarget.value = null; }

function onItemSaved(item) {
    const idx = itemsList.value.findIndex(i => i.dbId === item.dbId);
    if (idx !== -1) itemsList.value[idx] = item;
    else itemsList.value.unshift(item);
    justAddedItem.value = item.id;
    clearItemFilters();
    closeItemModal();
    setTimeout(() => { justAddedItem.value = null; }, 2600);
}

// ── Delete bundle ─────────────────────────────────────────────────────────────
const confirmDeleteBundle = ref(null);
const deletingBundle = ref(false);
const deleteBundleError = ref(null);
function askDeleteBundle(o) { confirmDeleteBundle.value = o; deleteBundleError.value = null; }
async function confirmDeleteBundleFn() {
    if (!confirmDeleteBundle.value) return;
    deletingBundle.value = true;
    deleteBundleError.value = null;
    try {
        await axios.delete(route('mp.service-options.destroy', confirmDeleteBundle.value.dbId));
        optionsList.value = optionsList.value.filter(o => o.dbId !== confirmDeleteBundle.value.dbId);
        confirmDeleteBundle.value = null;
    } catch (e) {
        deleteBundleError.value = e.response?.status === 403
            ? "You don't have permission to remove bundles."
            : 'Could not remove this bundle.';
    } finally {
        deletingBundle.value = false;
    }
}

// ── Delete service option (library item) ─────────────────────────────────────
const confirmDeleteItem = ref(null);
const deletingItem = ref(false);
const deleteItemError = ref(null);
function askDeleteItem(i) { confirmDeleteItem.value = i; deleteItemError.value = null; }
async function confirmDeleteItemFn() {
    if (!confirmDeleteItem.value) return;
    deletingItem.value = true;
    deleteItemError.value = null;
    try {
        await axios.delete(route('mp.service-option-items.destroy', confirmDeleteItem.value.dbId));
        itemsList.value = itemsList.value.filter(i => i.dbId !== confirmDeleteItem.value.dbId);
        confirmDeleteItem.value = null;
    } catch (e) {
        deleteItemError.value = e.response?.status === 403
            ? "You don't have permission to remove service options."
            : 'Could not remove this service option.';
    } finally {
        deletingItem.value = false;
    }
}
</script>

<template>
    <div class="mp-page">
        <div class="mp-page-head">
            <div>
                <h1 class="mp-page-title">Service options</h1>
                <p class="mp-page-sub">{{ eventItemsList.length }} reusable service options · {{ all.length }} bundles · {{ suppliers.length }} suppliers · a bundle can group one or many service options</p>
            </div>
            <div class="mp-head-actions">
                <RefreshButton :refreshing="refreshing" title="Refresh table" @click="emit('refresh')"/>
                <button class="mp-btn">Export</button>
                <button v-if="activeTab === 'options'" class="mp-btn mp-btn-primary" @click="openAddItem">+ New service option</button>
                <button v-else class="mp-btn mp-btn-primary" @click="openAddBundle">+ New bundle</button>
            </div>
        </div>

        <div class="mp-tabs">
            <button class="mp-tab" :class="{ 'mp-tab-on': activeTab === 'options' }" @click="activeTab = 'options'">Service Options · {{ eventItemsList.length }}</button>
            <button class="mp-tab" :class="{ 'mp-tab-on': activeTab === 'bundles' }" @click="activeTab = 'bundles'">Bundles · {{ all.length }}</button>
        </div>

        <!-- ══════════════════════════ Service Options tab ══════════════════════════ -->
        <template v-if="activeTab === 'options'">
            <div class="opt-strip">
                <div class="cox-kpi">
                    <div class="cox-kpi-l">Reusable service options</div>
                    <div class="cox-kpi-v mono">{{ eventItemsList.length }}</div>
                    <div class="cox-kpi-s">the library any bundle can pick from</div>
                </div>
                <div class="cox-kpi">
                    <div class="cox-kpi-l">In use</div>
                    <div class="cox-kpi-v mono">{{ usedItemCount }}</div>
                    <div class="cox-kpi-s">used in at least one bundle</div>
                </div>
                <div class="cox-kpi">
                    <div class="cox-kpi-l">Unused</div>
                    <div class="cox-kpi-v mono">{{ eventItemsList.length - usedItemCount }}</div>
                    <div class="cox-kpi-s">not yet added to any bundle</div>
                </div>
            </div>

            <div class="opt-toolbar">
                <div class="fb-search">
                    <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5" stroke-linecap="round"/></svg>
                    <input v-model="iq" placeholder="Find a service option or supplier…"/>
                </div>
                <FilterPanel :active-filters="itemActiveFilterChips" @clear-all="clearItemFilters">
                    <div class="fp-section">
                        <div class="fp-field">
                            <label>Supplier</label>
                            <select v-model="iSupplier">
                                <option value="all">All suppliers</option>
                                <option v-for="s in suppliers" :key="s.id" :value="s.code">{{ s.name }}</option>
                            </select>
                        </div>
                        <div class="fp-field">
                            <label>Item group</label>
                            <select v-model="iGroup">
                                <option value="all">All groups</option>
                                <option v-for="g in eventItemGroups" :key="g.id" :value="g.id">{{ g.label }}</option>
                            </select>
                        </div>
                    </div>
                </FilterPanel>
            </div>

            <div v-if="itemActiveFilterChips.length" class="opt-chips">
                <span v-for="f in itemActiveFilterChips" :key="f.key" class="opt-chip">
                    {{ f.label }}
                    <button type="button" class="opt-chip-x" @click="f.remove()" aria-label="Remove filter">
                        <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18"/></svg>
                    </button>
                </span>
            </div>

            <div class="mp-card mp-card-flush">
                <table class="mp-dt opt-table">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Name</th>
                            <th>Group</th>
                            <th>Supplier</th>
                            <th class="ta-r">Cost</th>
                            <th class="ta-r">Lead</th>
                            <th>SLA</th>
                            <th>Contract</th>
                            <th class="ta-c">Usage</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="i in itemRows" :key="i.dbId" :class="{ 'cat-row-new': justAddedItem === i.id }">
                            <td class="mono">{{ i.id }}</td>
                            <td class="opt-item-name">{{ i.name }}</td>
                            <td>
                                <span v-if="i.itemGroupLabel">{{ i.itemGroupLabel }}</span>
                                <span v-else class="mp-muted">—</span>
                                <div v-if="i.itemSubgroupLabel" class="opt-item-sub">{{ i.itemSubgroupLabel }}</div>
                            </td>
                            <td>{{ i.supplierName }}</td>
                            <td class="ta-r mono">{{ fmtMoney(i.cost) }}</td>
                            <td class="ta-r mono">{{ i.lead }} d</td>
                            <td>{{ i.sla || '—' }}</td>
                            <td class="mono">{{ i.contract }}</td>
                            <td class="ta-c">
                                <span class="opt-usage" :class="{ 'opt-usage-zero': !i.usageCount }">{{ i.usageCount }}</span>
                            </td>
                            <td class="ta-r mp-dt-actions">
                                <button v-if="permissions.isAdmin" class="mp-icon-btn mp-icon-edit" title="Edit" @click="openEditItem(i)"><i class="bx bx-pencil"></i></button>
                                <button v-if="permissions.isAdmin" class="mp-icon-btn mp-icon-del" title="Delete" @click="askDeleteItem(i)"><i class="bx bx-trash"></i></button>
                            </td>
                        </tr>
                        <tr v-if="!itemRows.length">
                            <td colspan="10" class="opt-empty">No service options match these filters.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="dt-foot">Showing <b>{{ itemRows.length }}</b> of {{ eventItemsList.length }} service options</div>
        </template>

        <!-- ══════════════════════════════ Bundles tab ═══════════════════════════════ -->
        <template v-if="activeTab === 'bundles'">
            <!-- KPI strip -->
            <div class="opt-strip">
                <div class="cox-kpi">
                    <div class="cox-kpi-l">Options per bundle</div>
                    <div class="cox-kpi-v mono">{{ avgServicesPerBundle }}</div>
                    <div class="cox-kpi-s">average across {{ all.length }} bundles</div>
                </div>
                <div class="cox-kpi">
                    <div class="cox-kpi-l">Median lead time</div>
                    <div class="cox-kpi-v mono">{{ avgLead }} d</div>
                    <div class="cox-kpi-s">own pool 2 d · managed services up to 30 d</div>
                </div>
                <div class="cox-kpi">
                    <div class="cox-kpi-l">Suspended bundles</div>
                    <div class="cox-kpi-v mono">{{ suspendedCount }}</div>
                    <div class="cox-kpi-s">not selectable until the supplier is reinstated</div>
                </div>
                <div class="cox-kpi">
                    <div class="cox-kpi-l">Missing a contract</div>
                    <div class="cox-kpi-v mono">{{ noContractCount }}</div>
                    <div class="cox-kpi-s">third-party services with no CTR reference</div>
                </div>
            </div>

            <!-- Toolbar -->
            <div class="opt-toolbar">
                <div class="fb-search">
                    <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5" stroke-linecap="round"/></svg>
                    <input v-model="q" placeholder="Find a bundle, service or supplier…"/>
                </div>
                <FilterPanel :active-filters="bundleActiveFilterChips" @clear-all="clearBundleFilters">
                    <div class="fp-section">
                        <div class="fp-field">
                            <label>Supplier</label>
                            <select v-model="fSupplier">
                                <option value="all">All suppliers</option>
                                <option v-for="s in suppliers" :key="s.id" :value="s.code">{{ s.name }}</option>
                            </select>
                        </div>
                        <div class="fp-field">
                            <label>Classification</label>
                            <select v-model="fClassification">
                                <option value="all">Any classification</option>
                                <option v-for="c in classifications" :key="c.id" :value="c.id">{{ c.name }}</option>
                            </select>
                        </div>
                        <div class="fp-field">
                            <label>Item group</label>
                            <select v-model="fGroup">
                                <option value="all">All groups</option>
                                <option v-for="g in eventItemGroups" :key="g.id" :value="g.id">{{ g.label }}</option>
                            </select>
                        </div>
                    </div>
                </FilterPanel>
            </div>

            <div v-if="bundleActiveFilterChips.length" class="opt-chips">
                <span v-for="f in bundleActiveFilterChips" :key="f.key" class="opt-chip">
                    {{ f.label }}
                    <button type="button" class="opt-chip-x" @click="f.remove()" aria-label="Remove filter">
                        <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18"/></svg>
                    </button>
                </span>
            </div>

            <!-- Bundles table -->
            <div class="mp-card mp-card-flush">
                <table class="mp-dt opt-table">
                    <thead>
                        <tr>
                            <th>Bundle</th>
                            <th>Group</th>
                            <th>Service options</th>
                            <th class="ta-r">Total cost</th>
                            <th class="ta-r">Bundle lead</th>
                            <th>Classification</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="o in rows" :key="o.id" :class="{ 'cat-row-new': justAddedBundle === o.id }">
                            <td>
                                <div class="opt-name">
                                    {{ o.name }}
                                    <span v-if="o.isDefault" class="opt-def">default</span>
                                </div>
                                <div class="opt-item-sub mono">{{ o.id }}</div>
                            </td>
                            <td>
                                <span v-if="o.itemGroupLabel">{{ o.itemGroupLabel }}</span>
                                <span v-else class="mp-muted">—</span>
                                <div v-if="o.itemSubgroupLabel" class="opt-item-sub">{{ o.itemSubgroupLabel }}</div>
                            </td>
                            <td>
                                <div class="opt-svcs">
                                    <span v-for="s in o.services" :key="s.id" class="opt-svc-chip">
                                        {{ s.name }} <span class="mono">· {{ supplierOf(s.supplier)?.name ?? s.supplier }} · {{ fmtMoney(s.cost) }} · {{ s.lead }}d</span>
                                    </span>
                                </div>
                            </td>
                            <td class="ta-r mono">{{ fmtMoney(o.cost) }}</td>
                            <td class="ta-r mono">{{ o.lead }} d</td>
                            <td><span class="opt-status" :style="{ background: colorMeta(o.classificationColor).bg, color: colorMeta(o.classificationColor).fg }">{{ o.classificationName || '—' }}</span></td>
                            <td class="ta-r mp-dt-actions">
                                <button v-if="permissions.isAdmin" class="mp-icon-btn mp-icon-edit" title="Edit" @click="openEditBundle(o)"><i class="bx bx-pencil"></i></button>
                                <button v-if="permissions.isAdmin" class="mp-icon-btn mp-icon-del" title="Delete" @click="askDeleteBundle(o)"><i class="bx bx-trash"></i></button>
                            </td>
                        </tr>
                        <tr v-if="!rows.length">
                            <td colspan="7" class="opt-empty">No bundles match these filters.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="dt-foot">Showing <b>{{ rows.length }}</b> of {{ all.length }} bundles</div>
        </template>
    </div>

    <!-- ── New / edit service option (library item) modal ────────────────────── -->
    <NewServiceOptionItemModal
        v-if="showAddItem"
        :suppliers="suppliers"
        :item-groups="eventItemGroups"
        :item-subgroups="eventItemSubgroups"
        :edit-item="editItemTarget"
        :event="event"
        @close="closeItemModal"
        @add="onItemSaved"
    />

    <!-- ── New / edit bundle modal ─────────────────────────────────────────────── -->
    <NewBundleModal
        v-if="showAddBundle"
        :service-option-items="eventItemsList"
        :classifications="classifications"
        :item-groups="eventItemGroups"
        :item-subgroups="eventItemSubgroups"
        :edit-option="editBundleTarget"
        :event="event"
        @close="closeBundleModal"
        @add="onBundleSaved"
    />

    <ConfirmModal
        v-if="confirmDeleteItem"
        :title="`Remove ${confirmDeleteItem.name}?`"
        :message="confirmDeleteItem.usageCount ? `Used in ${confirmDeleteItem.usageCount} bundle${confirmDeleteItem.usageCount === 1 ? '' : 's'} — removing it will drop it from all of them.` : ''"
        confirm-text="Remove"
        loading-text="Removing…"
        :loading="deletingItem"
        danger
        @cancel="confirmDeleteItem = null"
        @confirm="confirmDeleteItemFn"
    >
        <p v-if="deleteItemError" class="cfm-err">{{ deleteItemError }}</p>
    </ConfirmModal>

    <ConfirmModal
        v-if="confirmDeleteBundle"
        :title="`Remove ${confirmDeleteBundle.name}?`"
        confirm-text="Remove"
        loading-text="Removing…"
        :loading="deletingBundle"
        danger
        @cancel="confirmDeleteBundle = null"
        @confirm="confirmDeleteBundleFn"
    >
        <p v-if="deleteBundleError" class="cfm-err">{{ deleteBundleError }}</p>
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
.mp-btn:disabled { opacity: .4; cursor: not-allowed; }
.mp-btn-primary { background: #1a1614; border-color: #1a1614; color: #fff; }
.mp-btn-primary:hover { background: #0a0806; border-color: #0a0806; }

.mp-card { background: #fff; border: 1px solid #e8e4db; border-radius: 10px; margin-bottom: 16px; box-shadow: 0 1px 0 rgba(20,16,12,.03), 0 1px 2px rgba(20,16,12,.04); padding: 18px; }
.mp-card-flush { padding: 0; overflow: hidden; }
.mp-card-head { display: flex; align-items: baseline; justify-content: space-between; gap: 16px; margin-bottom: 14px; }
.mp-card-title { font-size: 14.5px; font-weight: 600; letter-spacing: -.01em; color: #1a1614; margin: 0; }
.mp-card-sub { font-size: 12px; color: #76706a; }

/* ── Tabs ─────────────────────────────────────────────────────────────────── */
.mp-tabs { display: flex; gap: 0; border-bottom: 2px solid #e8e4db; margin-bottom: 16px; }
.mp-tab {
    padding: 9px 16px; border: none; background: none; cursor: pointer;
    font-size: 13px; color: #76706a; border-bottom: 2px solid transparent;
    margin-bottom: -2px; transition: color .15s;
}
.mp-tab:hover { color: #1a1614; }
.mp-tab-on { color: #0f766e; border-bottom-color: #0f766e; font-weight: 600; }

/* ── KPI strip ────────────────────────────────────────────────────────────── */
.opt-strip {
    display: grid; grid-template-columns: repeat(4, 1fr); gap: 0;
    background: #fff; border: 1px solid #e8e4db; border-radius: 10px;
    margin-bottom: 16px; box-shadow: 0 1px 0 rgba(20,16,12,.03), 0 1px 2px rgba(20,16,12,.04);
}
.opt-strip:has(.cox-kpi:nth-child(3):last-child) { grid-template-columns: repeat(3, 1fr); }
.cox-kpi { padding: 14px 18px; border-right: 1px solid #efece4; }
.cox-kpi:last-child { border-right: none; }
.cox-kpi-l { font-size: 11px; color: #76706a; text-transform: uppercase; letter-spacing: .06em; font-weight: 600; margin-bottom: 6px; }
.cox-kpi-v { font-size: 22px; font-weight: 700; letter-spacing: -.02em; color: #1a1614; }
.cox-kpi-s { font-size: 11px; color: #76706a; margin-top: 4px; line-height: 1.4; }

/* ── Toolbar ──────────────────────────────────────────────────────────────── */
.opt-toolbar { display: flex; align-items: center; gap: 8px; margin-bottom: 10px; }
.fb-search {
    display: flex; align-items: center; gap: 8px;
    background: #fff; border: 1px solid #e8e4db; border-radius: 6px; padding: 6px 10px; color: #76706a; flex: 1;
}
.fb-search input { border: 0; outline: none; background: transparent; flex: 1; font-size: 12.5px; color: #1a1614; }

/* ── Filter panel sections (rendered inside <FilterPanel>'s slot) ───────────── */
.fp-section { padding: 2px 0; }
.fp-field { display: flex; flex-direction: column; gap: 4px; margin-bottom: 10px; }
.fp-field:last-child { margin-bottom: 0; }
.fp-field label { font-size: 10.5px; color: #76706a; text-transform: uppercase; letter-spacing: .06em; }
.fp-field select {
    appearance: none;
    background: #fff url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24'%3E%3Cpath fill='none' stroke='%2376706a' stroke-width='2' stroke-linecap='round' stroke-linejoin='round' d='M6 9l6 6 6-6'/%3E%3C/svg%3E") no-repeat right 8px center;
    border: 1px solid #e8e4db; border-radius: 6px;
    padding: 7px 24px 7px 10px; font-size: 12.5px; color: #1a1614; cursor: pointer; outline: none;
}

/* ── Active filter chips ──────────────────────────────────────────────────── */
.opt-chips { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 12px; }
.opt-chip {
    display: inline-flex; align-items: center; gap: 6px;
    font-size: 12px; font-weight: 500; color: #3d3833; background: #efece4;
    padding: 4px 6px 4px 10px; border-radius: 20px;
}
.opt-chip-x {
    width: 15px; height: 15px; border-radius: 50%; border: none; background: rgba(61,56,51,.12);
    color: #3d3833; display: inline-flex; align-items: center; justify-content: center; cursor: pointer;
}
.opt-chip-x:hover { background: rgba(61,56,51,.25); }

/* ── Options table ────────────────────────────────────────────────────────── */
.mp-dt { width: 100%; border-collapse: collapse; font-size: 13px; }
.mp-dt th {
    background: #fbfaf6; border-bottom: 1px solid #e8e4db;
    color: #76706a; font-size: 11px; text-transform: uppercase; letter-spacing: .05em;
    padding: 10px 14px; text-align: left; white-space: nowrap;
}
.mp-dt td { padding: 11px 14px; border-bottom: 1px solid #f3f0ea; vertical-align: middle; color: #1a1614; }
.mp-dt tr:last-child td { border-bottom: none; }
.opt-table th.ta-c { text-align: center; }

.opt-item-sub { font-size: 11px; color: #76706a; margin-top: 2px; }
.opt-item-name { font-size: 13px; font-weight: 500; }
.opt-name { font-size: 13px; font-weight: 500; display: flex; align-items: center; gap: 6px; }
.opt-def {
    font-size: 10px; font-weight: 600; text-transform: uppercase; letter-spacing: .04em;
    background: #ccfbf1; color: #0f766e; padding: 1px 6px; border-radius: 4px;
}
.opt-svcs { display: flex; flex-wrap: wrap; gap: 5px; max-width: 340px; }
.opt-svc-chip { font-size: 11px; color: #3d3833; background: #efece4; padding: 2px 7px; border-radius: 20px; white-space: nowrap; }
.opt-status {
    display: inline-flex; align-items: center; padding: 2px 8px;
    border-radius: 20px; font-size: 11px; font-weight: 600; white-space: nowrap;
}
.opt-usage {
    display: inline-flex; align-items: center; justify-content: center; min-width: 22px;
    padding: 2px 7px; border-radius: 20px; font-size: 11.5px; font-weight: 700;
    background: #ccfbf1; color: #0f766e;
}
.opt-usage-zero { background: #efece4; color: #76706a; }
.opt-empty { text-align: center; padding: 20px; color: #76706a; font-size: 12.5px; }
.mp-muted { color: #a39c94; }

.mp-dt-actions { display: flex; gap: 4px; justify-content: flex-end; white-space: nowrap; }
.mp-icon-btn { width: 30px; height: 30px; border-radius: 6px; border: 1px solid transparent; display: inline-flex; align-items: center; justify-content: center; cursor: pointer; font-size: 14px; transition: background .15s; }
.mp-icon-edit { background: #fff7e6; border-color: #fde7b0; color: #d97706; }
.mp-icon-edit:hover { background: #fef3c7; }
.mp-icon-del { background: #fff1f2; border-color: #fecdd3; color: #dc2626; }
.mp-icon-del:hover { background: #ffe4e6; }
.cfm-err { font-size: 12.5px; color: #991b1b; margin-top: 8px; }

@keyframes cat-newrow { 0%, 100% { background: transparent; } 20% { background: rgba(15,118,110,.12); } }
.cat-row-new td { animation: cat-newrow 2.4s ease; }

.dt-foot { font-size: 12px; color: #76706a; text-align: right; margin: 8px 0 16px; }

.mono { font-family: ui-monospace, 'SF Mono', Menlo, monospace; }
.ta-r { text-align: right; }
.ta-c { text-align: center; }

</style>
