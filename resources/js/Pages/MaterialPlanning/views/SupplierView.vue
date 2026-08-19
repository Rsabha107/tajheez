<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import axios from 'axios';

const props = defineProps({
    suppliers:   Array,
    people:      { type: Array, default: () => [] },
    permissions: { type: Object, default: () => ({ isAdmin: false, managedDomain: null }) },
    event:       { type: Object, default: () => ({ code: 'EVT' }) },
});

// Local writable copy so we can optimistically reflect writes
const supplierList = ref([...props.suppliers]);

// ── Filters ───────────────────────────────────────────────────────────────────
const q = ref('');
const fStatus = ref('all');

function personOf(ini) { return (props.people || []).find(p => p.initials === ini) || { name: ini }; }
function fmtDot(ini) {
    const colors = ['#7c2d12', '#0f766e', '#b45309', '#1d4ed8', '#6b21a8', '#155e75', '#854d0e'];
    if (!ini) return colors[0];
    const h = (ini.charCodeAt(0) + (ini.charCodeAt(1) || 0)) % colors.length;
    return colors[h];
}

const STATUS_META = {
    preferred: { label: 'Preferred', bg: '#dcfce7', fg: '#166534' },
    active:    { label: 'Active',    bg: '#efece4', fg: '#3d3833' },
    suspended: { label: 'Suspended', bg: '#fee2e2', fg: '#991b1b' },
};
function statusMeta(k) { return STATUS_META[k] || STATUS_META.active; }

const rows = computed(() => {
    let r = supplierList.value.slice();
    if (fStatus.value !== 'all') r = r.filter(s => s.status === fStatus.value);
    if (q.value) {
        const k = q.value.toLowerCase();
        r = r.filter(s => s.name.toLowerCase().includes(k) || s.kind.toLowerCase().includes(k) || s.id.toLowerCase().includes(k));
    }
    return r.sort((a, b) => a.name.localeCompare(b.name));
});

const kpi = computed(() => ({
    preferred: supplierList.value.filter(s => s.status === 'preferred').length,
    active: supplierList.value.filter(s => s.status === 'active').length,
    suspended: supplierList.value.filter(s => s.status === 'suspended').length,
    noMsa: supplierList.value.filter(s => s.msa === '—').length,
}));

// ── New supplier modal ──────────────────────────────────────────────────────
const showAdd = ref(false);
const saving = ref(false);
const error = ref(null);
const justAdded = ref(null);

function freshForm() {
    return { code: '', name: '', kind: '', status: 'active', msa: '', owner_user_id: '' };
}
const form = ref(freshForm());
const formValid = computed(() => /^SUP-[A-Z0-9]+$/.test(form.value.code) && form.value.name.trim() && form.value.kind.trim());

function openAdd() {
    form.value = freshForm();
    error.value = null;
    showAdd.value = true;
}
function closeAdd() { showAdd.value = false; }

async function addSupplier() {
    if (!formValid.value || saving.value) return;
    saving.value = true;
    error.value = null;
    try {
        const { data } = await axios.post(route('mp.suppliers.store'), {
            code: form.value.code.trim().toUpperCase(),
            name: form.value.name.trim(),
            kind: form.value.kind.trim(),
            status: form.value.status,
            msa_reference: form.value.msa.trim() || null,
            owner_user_id: form.value.owner_user_id || null,
        });
        supplierList.value.unshift(data);
        justAdded.value = data.id;
        closeAdd();
        setTimeout(() => { justAdded.value = null; }, 2400);
    } catch (e) {
        error.value = e.response?.status === 403
            ? "You don't have permission to add a supplier."
            : (e.response?.data?.errors?.code?.[0] ?? 'Could not save this supplier. Please try again.');
    } finally {
        saving.value = false;
    }
}

async function deleteSupplier(supplier) {
    if (!confirm(`Remove ${supplier.name}?`)) return;
    try {
        await axios.delete(route('mp.suppliers.destroy', supplier.id));
        supplierList.value = supplierList.value.filter(s => s.id !== supplier.id);
    } catch (e) {
        alert(e.response?.status === 403 ? "You don't have permission to remove suppliers." : 'Could not remove this supplier.');
    }
}

function onEsc(e) { if (e.key === 'Escape' && showAdd.value) closeAdd(); }
onMounted(()   => document.addEventListener('keydown', onEsc));
onUnmounted(() => document.removeEventListener('keydown', onEsc));
</script>

<template>
    <div class="mp-page">
        <div class="mp-page-head">
            <div>
                <h1 class="mp-page-title">Suppliers</h1>
                <p class="mp-page-sub">{{ supplierList.length }} suppliers · framework agreements in force for {{ event.code }}</p>
            </div>
            <div class="mp-head-actions">
                <button v-if="permissions.isAdmin" class="mp-btn mp-btn-primary" @click="openAdd">+ New supplier</button>
            </div>
        </div>

        <!-- KPI strip -->
        <div class="sup-strip">
            <div class="cox-kpi">
                <div class="cox-kpi-l">Preferred</div>
                <div class="cox-kpi-v mono">{{ kpi.preferred }}</div>
            </div>
            <div class="cox-kpi">
                <div class="cox-kpi-l">Active</div>
                <div class="cox-kpi-v mono">{{ kpi.active }}</div>
            </div>
            <div class="cox-kpi">
                <div class="cox-kpi-l">Suspended</div>
                <div class="cox-kpi-v mono">{{ kpi.suspended }}</div>
            </div>
            <div class="cox-kpi">
                <div class="cox-kpi-l">Missing an MSA</div>
                <div class="cox-kpi-v mono">{{ kpi.noMsa }}</div>
            </div>
        </div>

        <!-- Filter bar -->
        <div class="filterbar">
            <div class="fb-search">
                <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5" stroke-linecap="round"/></svg>
                <input v-model="q" placeholder="Find a supplier by name, kind or code…"/>
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

        <!-- Table -->
        <div class="mp-card mp-card-flush">
            <table class="mp-dt">
                <thead>
                    <tr>
                        <th>Supplier</th>
                        <th>Kind</th>
                        <th>Status</th>
                        <th>MSA</th>
                        <th>Owner</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="s in rows" :key="s.id" :class="{ 'sup-row-new': justAdded === s.id }">
                        <td>
                            <div class="sup-name">{{ s.name }}</div>
                            <div class="sup-code mono">{{ s.id }}</div>
                        </td>
                        <td>{{ s.kind }}</td>
                        <td><span class="opt-status" :style="{ background: statusMeta(s.status).bg, color: statusMeta(s.status).fg }">{{ statusMeta(s.status).label }}</span></td>
                        <td class="mono">{{ s.msa }}</td>
                        <td>
                            <div class="sup-owner">
                                <span class="mp-avatar mp-avatar-sm" :style="{ background: fmtDot(s.owner) }">{{ s.owner }}</span>
                                {{ personOf(s.owner).name }}
                            </div>
                        </td>
                        <td class="ta-r">
                            <button v-if="permissions.isAdmin" class="mp-btn mp-btn-sm" @click="deleteSupplier(s)">Remove</button>
                        </td>
                    </tr>
                    <tr v-if="!rows.length">
                        <td colspan="6" class="sup-empty">No suppliers match these filters.</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="dt-foot">Showing <b>{{ rows.length }}</b> of {{ supplierList.length }} suppliers</div>
    </div>

    <!-- ── New supplier modal ──────────────────────────────────────────────── -->
    <Teleport to="body" v-if="showAdd">
        <div class="skum-scrim" @click.self="closeAdd">
            <div class="skum" role="dialog" aria-modal="true">
                <header class="skum-hd">
                    <div class="skum-hd-l">
                        <div class="skum-hd-tag"><span class="mono">{{ event.code }}</span><span>·</span><span>Suppliers</span></div>
                        <h2 class="skum-title">New supplier</h2>
                        <p class="skum-sub">Register a framework supplier that can back a catalog item's service options.</p>
                    </div>
                    <button class="skum-x" @click="closeAdd" aria-label="Close">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18"/></svg>
                    </button>
                </header>

                <div class="skum-body">
                    <section class="skum-sec">
                        <div class="form-grid">
                            <div class="field">
                                <label class="field-lbl">Code</label>
                                <input v-model="form.code" placeholder="e.g. SUP-ACME" class="mono"/>
                                <span class="field-hint">Must start with SUP- (letters/numbers only).</span>
                            </div>
                            <div class="field">
                                <label class="field-lbl">Status</label>
                                <div class="sel">
                                    <select v-model="form.status">
                                        <option value="preferred">Preferred</option>
                                        <option value="active">Active</option>
                                        <option value="suspended">Suspended</option>
                                    </select>
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6"/></svg>
                                </div>
                            </div>
                            <div class="field" style="grid-column: span 2">
                                <label class="field-lbl">Name</label>
                                <input v-model="form.name" placeholder="e.g. Acme Event Services"/>
                            </div>
                            <div class="field">
                                <label class="field-lbl">Kind</label>
                                <input v-model="form.kind" placeholder="e.g. Overlay & furniture"/>
                            </div>
                            <div class="field">
                                <label class="field-lbl">MSA reference</label>
                                <input v-model="form.msa" placeholder="optional"/>
                            </div>
                            <div class="field" style="grid-column: span 2">
                                <label class="field-lbl">Owner</label>
                                <div class="sel">
                                    <select v-model="form.owner_user_id">
                                        <option value="">— Unassigned —</option>
                                        <option v-for="p in people" :key="p.initials" :value="p.id">{{ p.name }}</option>
                                    </select>
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6"/></svg>
                                </div>
                            </div>
                        </div>
                    </section>
                </div>

                <footer class="skum-ft">
                    <div class="skum-ft-l">
                        <span v-if="error" class="skum-ft-warn"><span class="skum-ft-dot" style="background:#b45309"></span>{{ error }}</span>
                        <span v-else-if="formValid" class="skum-ft-ok"><span class="skum-ft-dot" style="background:#16a34a"></span>Ready to add</span>
                        <span v-else class="skum-ft-warn"><span class="skum-ft-dot" style="background:#b45309"></span>Code, name and kind are required</span>
                    </div>
                    <div class="skum-ft-r">
                        <button class="mp-btn" @click="closeAdd">Cancel</button>
                        <button class="mp-btn mp-btn-primary" :disabled="!formValid || saving" @click="addSupplier">{{ saving ? 'Adding…' : 'Add supplier' }}</button>
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
.mp-btn-sm { padding: 4px 10px; font-size: 12px; }

.mp-card { background: #fff; border: 1px solid #e8e4db; border-radius: 10px; margin-bottom: 16px; box-shadow: 0 1px 0 rgba(20,16,12,.03), 0 1px 2px rgba(20,16,12,.04); padding: 18px; }
.mp-card-flush { padding: 0; overflow: hidden; }

/* ── KPI strip ────────────────────────────────────────────────────────────── */
.sup-strip {
    display: grid; grid-template-columns: repeat(4, 1fr); gap: 0;
    background: #fff; border: 1px solid #e8e4db; border-radius: 10px;
    margin-bottom: 16px; box-shadow: 0 1px 0 rgba(20,16,12,.03), 0 1px 2px rgba(20,16,12,.04);
}
.cox-kpi { padding: 14px 18px; border-right: 1px solid #efece4; }
.cox-kpi:last-child { border-right: none; }
.cox-kpi-l { font-size: 11px; color: #76706a; text-transform: uppercase; letter-spacing: .06em; font-weight: 600; margin-bottom: 6px; }
.cox-kpi-v { font-size: 22px; font-weight: 700; letter-spacing: -.02em; color: #1a1614; }

/* ── Filter bar ───────────────────────────────────────────────────────────── */
.filterbar { display: flex; align-items: center; gap: 8px; margin-bottom: 12px; }
.fb-search {
    flex: 1; display: flex; align-items: center; gap: 8px;
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

/* ── Table ────────────────────────────────────────────────────────────────── */
.mp-dt { width: 100%; border-collapse: collapse; font-size: 13px; }
.mp-dt th {
    background: #fbfaf6; border-bottom: 1px solid #e8e4db;
    color: #76706a; font-size: 11px; text-transform: uppercase; letter-spacing: .05em;
    padding: 10px 14px; text-align: left; white-space: nowrap;
}
.mp-dt td { padding: 11px 14px; border-bottom: 1px solid #f3f0ea; vertical-align: middle; color: #1a1614; }
.mp-dt tr:last-child td { border-bottom: none; }
.sup-name { font-weight: 500; }
.sup-code { font-size: 11px; color: #76706a; margin-top: 2px; }
.sup-owner { display: flex; align-items: center; gap: 8px; }
.sup-empty { text-align: center; padding: 24px; color: #76706a; }

.opt-status {
    display: inline-flex; align-items: center; padding: 2px 8px;
    border-radius: 20px; font-size: 11px; font-weight: 600; white-space: nowrap;
}
.mp-avatar {
    display: inline-flex; align-items: center; justify-content: center;
    width: 22px; height: 22px; border-radius: 50%;
    color: #fff; font-size: 9px; font-weight: 700; flex-shrink: 0;
}

@keyframes sup-newrow { 0%, 100% { background: transparent; } 20% { background: rgba(15,118,110,.12); } }
.sup-row-new td { animation: sup-newrow 2.4s ease; }

.dt-foot { font-size: 12px; color: #76706a; text-align: right; margin: 8px 0 16px; }
.mono { font-family: ui-monospace, 'SF Mono', Menlo, monospace; }
.ta-r { text-align: right; }

/* ── Modal shell ──────────────────────────────────────────────────────────── */
@keyframes skum-fade { from { opacity: 0; } to { opacity: 1; } }
@keyframes skum-pop  { from { opacity: 0; transform: translateY(14px) scale(.97); } to { opacity: 1; transform: none; } }
.skum-scrim {
    position: fixed; inset: 0; z-index: 1000;
    background: rgba(26,22,20,.45);
    display: flex; align-items: flex-start; justify-content: center;
    padding: 40px 16px; overflow-y: auto;
    animation: skum-fade .18s ease;
}
.skum {
    background: #fff; border: 1px solid #e8e4db; border-radius: 14px;
    width: 100%; max-width: 560px;
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
    cursor: pointer; color: #76706a; flex-shrink: 0; margin-left: 12px; transition: background .12s;
}
.skum-x:hover { background: #f6f5f1; }
.skum-body { padding: 0 24px; overflow-y: auto; max-height: 62vh; }
.skum-sec { padding: 20px 0; }

.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
.field { display: flex; flex-direction: column; gap: 5px; }
.field-lbl { font-size: 11.5px; font-weight: 600; color: #3d3833; }
.field-hint { font-size: 11px; color: #76706a; }
.field input {
    border: 1px solid #e8e4db; border-radius: 7px; padding: 8px 11px;
    font-size: 13px; color: #1a1614; background: #fff; outline: none; transition: border-color .12s;
}
.field input:focus { border-color: #0f766e; box-shadow: 0 0 0 3px rgba(15,118,110,.1); }
.sel { position: relative; display: flex; align-items: center; }
.sel select {
    width: 100%; appearance: none; border: 1px solid #e8e4db; border-radius: 7px;
    padding: 8px 30px 8px 11px; font-size: 13px; color: #1a1614; background: #fff;
    outline: none; cursor: pointer; transition: border-color .12s;
}
.sel select:focus { border-color: #0f766e; box-shadow: 0 0 0 3px rgba(15,118,110,.1); }
.sel svg { position: absolute; right: 10px; pointer-events: none; color: #76706a; }

.skum-ft {
    display: flex; align-items: center; justify-content: space-between;
    gap: 12px; padding: 16px 24px; border-top: 1px solid #e8e4db;
    background: #fbfaf6; border-radius: 0 0 13px 13px;
}
.skum-ft-l { display: flex; align-items: center; gap: 10px; min-width: 0; }
.skum-ft-r { display: flex; gap: 8px; flex-shrink: 0; }
.skum-ft-ok, .skum-ft-warn { display: flex; align-items: center; gap: 5px; font-size: 12px; }
.skum-ft-ok   { color: #16a34a; }
.skum-ft-warn { color: #b45309; }
.skum-ft-dot  { width: 6px; height: 6px; border-radius: 50%; flex-shrink: 0; }
</style>
