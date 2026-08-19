<script setup>
import { ref, computed } from 'vue';
import NewServiceOptionModal from '../components/NewServiceOptionModal.vue';

const props = defineProps({
    catalog:        Array,
    domains:        Array,
    suppliers:      Array,
    serviceOptions: Array,
    people:         Array,
    event:          { type: Object, default: () => ({ code: 'EVT' }) },
});

// Local writable copy so we can optimistically add options
const optionsList = ref([...props.serviceOptions]);

// ── Filters ───────────────────────────────────────────────────────────────────
const fSupplier = ref('all');
const fDomain   = ref('all');
const fStatus   = ref('all');
const q         = ref('');

// ── Helpers ───────────────────────────────────────────────────────────────────
function domainOf(id)   { return props.domains.find(d => d.id === id) || props.domains[0]; }
function catalogOf(sku) { return props.catalog.find(c => c.sku === sku); }
function supplierOf(id) { return props.suppliers.find(s => s.id === id) || props.suppliers[props.suppliers.length - 1]; }
function fmtMoney(n)    { return '$' + Number(n).toLocaleString('en-US'); }
function personOf(ini)  { return (props.people || []).find(p => p.initials === ini) || props.people?.[0] || { name: ini }; }

const avatarColors = ['#7c2d12', '#0f766e', '#b45309', '#1d4ed8', '#6b21a8', '#155e75', '#854d0e'];
function avatarColor(initials) {
    const h = (initials.charCodeAt(0) + (initials.charCodeAt(1) || 0)) % avatarColors.length;
    return avatarColors[h];
}

const OPT_STATUS = {
    preferred: { label: 'Preferred', bg: '#dcfce7', fg: '#166534' },
    active:    { label: 'Active',    bg: '#efece4', fg: '#3d3833' },
    suspended: { label: 'Suspended', bg: '#fee2e2', fg: '#991b1b' },
};
function statusMeta(k) { return OPT_STATUS[k] || OPT_STATUS.active; }
function variance(cost, rate) {
    const d = cost - rate;
    if (!rate || d === 0) return { flat: true, text: 'at catalog' };
    return { flat: false, pos: d > 0, text: (d > 0 ? '+' : '−') + fmtMoney(Math.abs(d)) };
}

// ── Derived ───────────────────────────────────────────────────────────────────
const all = computed(() =>
    optionsList.value
        .map(o => ({ ...o, item: catalogOf(o.sku) }))
        .filter(o => o.item)
);

const rows = computed(() => {
    let r = all.value.slice();
    if (fSupplier.value !== 'all') r = r.filter(o => o.supplier === fSupplier.value);
    if (fDomain.value !== 'all')   r = r.filter(o => o.item.domain === fDomain.value);
    if (fStatus.value !== 'all')   r = r.filter(o => o.status === fStatus.value);
    if (q.value) {
        const k = q.value.toLowerCase();
        r = r.filter(o =>
            o.name.toLowerCase().includes(k) || o.item.name.toLowerCase().includes(k) ||
            o.sku.toLowerCase().includes(k) || supplierOf(o.supplier).name.toLowerCase().includes(k)
        );
    }
    r.sort((a, b) => a.item.name.localeCompare(b.item.name) || (b.isDefault ? 1 : 0) - (a.isDefault ? 1 : 0));
    return r;
});

const multiChoiceCount = computed(() =>
    props.catalog.filter(c => all.value.filter(o => o.sku === c.sku).length > 1).length
);
const avgLead = computed(() => {
    if (!all.value.length) return 0;
    return Math.round(all.value.reduce((s, o) => s + o.lead, 0) / all.value.length);
});
const suspendedCount = computed(() => all.value.filter(o => o.status === 'suspended').length);
const noContractCount = computed(() =>
    all.value.filter(o => o.contract === '—' && o.supplier !== 'SUP-OWN' && o.supplier !== 'SUP-ITX').length
);

const suppliersRollup = computed(() =>
    props.suppliers.map(s => {
        const mine = all.value.filter(o => o.supplier === s.id);
        const lead = mine.length ? Math.round(mine.reduce((a, o) => a + o.lead, 0) / mine.length) : 0;
        return { ...s, count: mine.length, lead };
    })
);

// ── New service option modal ────────────────────────────────────────────────
const showAdd   = ref(false);
const justAdded = ref(null);

function openAdd()  { showAdd.value = true; }
function closeAdd() { showAdd.value = false; }

function onOptionAdded(option) {
    optionsList.value.unshift(option);
    justAdded.value = option.id;
    fSupplier.value = 'all'; fDomain.value = 'all'; fStatus.value = 'all'; q.value = '';
    closeAdd();
    setTimeout(() => { justAdded.value = null; }, 2600);
}
</script>

<template>
    <div class="mp-page">
        <div class="mp-page-head">
            <div>
                <h1 class="mp-page-title">Service options</h1>
                <p class="mp-page-sub">{{ all.length }} options across {{ catalog.length }} catalog items · {{ suppliers.length }} suppliers · cost here overrides the catalog rate on any line that selects it</p>
            </div>
            <div class="mp-head-actions">
                <button class="mp-btn">Export</button>
                <button class="mp-btn mp-btn-primary" @click="openAdd">+ New service option</button>
            </div>
        </div>

        <!-- KPI strip -->
        <div class="opt-strip">
            <div class="cox-kpi">
                <div class="cox-kpi-l">Items with a choice</div>
                <div class="cox-kpi-v mono">{{ multiChoiceCount }}</div>
                <div class="cox-kpi-s">of {{ catalog.length }} catalog items have more than one option</div>
            </div>
            <div class="cox-kpi">
                <div class="cox-kpi-l">Median lead time</div>
                <div class="cox-kpi-v mono">{{ avgLead }} d</div>
                <div class="cox-kpi-s">own pool 2 d · managed services up to 30 d</div>
            </div>
            <div class="cox-kpi">
                <div class="cox-kpi-l">Suspended options</div>
                <div class="cox-kpi-v mono">{{ suspendedCount }}</div>
                <div class="cox-kpi-s">not selectable until the supplier is reinstated</div>
            </div>
            <div class="cox-kpi">
                <div class="cox-kpi-l">Missing a contract</div>
                <div class="cox-kpi-v mono">{{ noContractCount }}</div>
                <div class="cox-kpi-s">third-party options with no CTR reference</div>
            </div>
        </div>

        <!-- Filter bar -->
        <div class="filterbar">
            <div class="fb-search">
                <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5" stroke-linecap="round"/></svg>
                <input v-model="q" placeholder="Find an option, item, SKU or supplier…"/>
            </div>
            <div class="fb-sel">
                <label>Supplier</label>
                <select v-model="fSupplier">
                    <option value="all">All suppliers</option>
                    <option v-for="s in suppliers" :key="s.id" :value="s.id">{{ s.name }}</option>
                </select>
            </div>
            <div class="fb-sel">
                <label>Domain</label>
                <select v-model="fDomain">
                    <option value="all">All</option>
                    <option v-for="d in domains" :key="d.id" :value="d.id">{{ d.label }}</option>
                </select>
            </div>
            <div class="fb-sel">
                <label>Status</label>
                <select v-model="fStatus">
                    <option value="all">Any status</option>
                    <option value="preferred">Preferred</option>
                    <option value="active">Active</option>
                    <option value="suspended">Suspended</option>
                </select>
            </div>
        </div>

        <!-- Options table -->
        <div class="mp-card mp-card-flush">
            <table class="mp-dt opt-table">
                <thead>
                    <tr>
                        <th>Catalog item</th>
                        <th>Service option</th>
                        <th>Supplier</th>
                        <th class="ta-r">Unit cost</th>
                        <th class="ta-r">vs catalog</th>
                        <th class="ta-r">Lead</th>
                        <th>SLA</th>
                        <th class="ta-r">Cap / venue</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="o in rows" :key="o.id" :class="{ 'cat-row-new': justAdded === o.id }">
                        <td>
                            <div class="opt-item">{{ o.item.name }}</div>
                            <div class="opt-item-sub"><span class="mono">{{ o.sku }}</span> · {{ o.item.group }}</div>
                        </td>
                        <td>
                            <div class="opt-name">
                                {{ o.name }}
                                <span v-if="o.isDefault" class="opt-def">default</span>
                            </div>
                            <div class="opt-spec">{{ o.spec }}</div>
                        </td>
                        <td>
                            <div class="opt-sup">{{ supplierOf(o.supplier).name }}</div>
                            <div class="opt-sup-sub mono">{{ o.contract }}</div>
                        </td>
                        <td class="ta-r mono">{{ fmtMoney(o.cost) }}<span class="opt-unit"> / {{ o.item.unit }}</span></td>
                        <td class="ta-r">
                            <span v-if="variance(o.cost, o.item.rate).flat" class="opt-var-flat">at catalog</span>
                            <span v-else class="mono" :class="variance(o.cost, o.item.rate).pos ? 'diff-pos' : 'diff-neg'">{{ variance(o.cost, o.item.rate).text }}</span>
                        </td>
                        <td class="ta-r mono">{{ o.lead }} d</td>
                        <td class="opt-sla">{{ o.sla }}</td>
                        <td class="ta-r mono">{{ o.capacity || '—' }}</td>
                        <td><span class="opt-status" :style="{ background: statusMeta(o.status).bg, color: statusMeta(o.status).fg }">{{ statusMeta(o.status).label }}</span></td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="dt-foot">Showing <b>{{ rows.length }}</b> of {{ all.length }} service options</div>

        <!-- Suppliers card -->
        <div class="mp-card">
            <div class="mp-card-head">
                <h3 class="mp-card-title">Suppliers</h3>
                <div class="mp-card-sub">Framework agreements in force for {{ event.code }}</div>
            </div>
            <ul class="opt-suplist">
                <li v-for="s in suppliersRollup" :key="s.id" class="opt-suprow" :class="{ 'opt-suprow-off': s.status === 'suspended' }">
                    <div class="opt-suprow-l">
                        <div class="opt-sup">{{ s.name }}</div>
                        <div class="opt-sup-sub">{{ s.kind }} · <span class="mono">{{ s.msa }}</span></div>
                    </div>
                    <div class="opt-supstat mono">{{ s.count }} option{{ s.count === 1 ? '' : 's' }}</div>
                    <div class="opt-supstat mono">{{ s.lead ? s.lead + ' d avg lead' : '—' }}</div>
                    <div class="opt-supown">
                        <span class="mp-avatar mp-avatar-sm" :style="{ background: avatarColor(s.owner) }">{{ s.owner }}</span>
                        {{ personOf(s.owner).name }}
                    </div>
                    <span class="opt-status" :style="{ background: statusMeta(s.status).bg, color: statusMeta(s.status).fg }">{{ statusMeta(s.status).label }}</span>
                </li>
            </ul>
        </div>
    </div>

    <!-- ── New service option modal ──────────────────────────────────────── -->
    <NewServiceOptionModal
        v-if="showAdd"
        :catalog="catalog"
        :suppliers="suppliers"
        :domains="domains"
        :service-options="optionsList"
        :event="event"
        @close="closeAdd"
        @add="onOptionAdded"
    />
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

/* ── KPI strip ────────────────────────────────────────────────────────────── */
.opt-strip {
    display: grid; grid-template-columns: repeat(4, 1fr); gap: 0;
    background: #fff; border: 1px solid #e8e4db; border-radius: 10px;
    margin-bottom: 16px; box-shadow: 0 1px 0 rgba(20,16,12,.03), 0 1px 2px rgba(20,16,12,.04);
}
.cox-kpi { padding: 14px 18px; border-right: 1px solid #efece4; }
.cox-kpi:last-child { border-right: none; }
.cox-kpi-l { font-size: 11px; color: #76706a; text-transform: uppercase; letter-spacing: .06em; font-weight: 600; margin-bottom: 6px; }
.cox-kpi-v { font-size: 22px; font-weight: 700; letter-spacing: -.02em; color: #1a1614; }
.cox-kpi-s { font-size: 11px; color: #76706a; margin-top: 4px; line-height: 1.4; }

/* ── Filter bar ───────────────────────────────────────────────────────────── */
.filterbar {
    display: grid; grid-template-columns: 1.6fr repeat(3, 1fr);
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

/* ── Options table ────────────────────────────────────────────────────────── */
.mp-dt { width: 100%; border-collapse: collapse; font-size: 13px; }
.mp-dt th {
    background: #fbfaf6; border-bottom: 1px solid #e8e4db;
    color: #76706a; font-size: 11px; text-transform: uppercase; letter-spacing: .05em;
    padding: 10px 14px; text-align: left; white-space: nowrap;
}
.mp-dt td { padding: 11px 14px; border-bottom: 1px solid #f3f0ea; vertical-align: middle; color: #1a1614; }
.mp-dt tr:last-child td { border-bottom: none; }

.opt-item { font-weight: 500; }
.opt-item-sub { font-size: 11px; color: #76706a; margin-top: 2px; }
.opt-name { font-size: 13px; font-weight: 500; display: flex; align-items: center; gap: 6px; }
.opt-def {
    font-size: 10px; font-weight: 600; text-transform: uppercase; letter-spacing: .04em;
    background: #ccfbf1; color: #0f766e; padding: 1px 6px; border-radius: 4px;
}
.opt-spec { font-size: 11px; color: #76706a; margin-top: 2px; max-width: 260px; }
.opt-sup { font-size: 13px; font-weight: 500; }
.opt-sup-sub { font-size: 11px; color: #76706a; margin-top: 2px; }
.opt-unit { font-size: 11px; color: #76706a; font-weight: 400; }
.opt-sla { font-size: 12.5px; }
.opt-var-flat { font-size: 12px; color: #76706a; }
.diff-pos { color: #166534; }
.diff-neg { color: #991b1b; }
.opt-status {
    display: inline-flex; align-items: center; padding: 2px 8px;
    border-radius: 20px; font-size: 11px; font-weight: 600; white-space: nowrap;
}

@keyframes cat-newrow { 0%, 100% { background: transparent; } 20% { background: rgba(15,118,110,.12); } }
.cat-row-new td { animation: cat-newrow 2.4s ease; }

/* ── Suppliers card ───────────────────────────────────────────────────────── */
.opt-suplist { list-style: none; margin: 0; padding: 0; }
.opt-suprow {
    display: grid; grid-template-columns: 1.6fr 100px 120px 160px 90px;
    align-items: center; gap: 12px; padding: 12px 0;
    border-bottom: 1px solid #f3f0ea;
}
.opt-suprow:last-child { border-bottom: none; }
.opt-suprow-off { opacity: .55; }
.opt-supstat { font-size: 12.5px; color: #76706a; }
.opt-supown { display: flex; align-items: center; gap: 8px; font-size: 12.5px; color: #1a1614; }

.mp-avatar {
    display: inline-flex; align-items: center; justify-content: center;
    width: 28px; height: 28px; border-radius: 50%;
    color: #fff; font-size: 11px; font-weight: 700; flex-shrink: 0;
}
.mp-avatar-sm { width: 20px; height: 20px; font-size: 9px; }

.mp-dtag { display: inline-flex; align-items: center; gap: 4px; padding: 2px 8px; border-radius: 5px; font-size: 12px; font-weight: 600; }

.dt-foot { font-size: 12px; color: #76706a; text-align: right; margin: 8px 0 16px; }

.mono { font-family: ui-monospace, 'SF Mono', Menlo, monospace; }
.ta-r { text-align: right; }

</style>
