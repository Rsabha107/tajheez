<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import axios from 'axios';
import ConfirmModal from '../components/ConfirmModal.vue';

const props = defineProps({
    domains:     Array,
    statuses:    { type: Array, default: () => [] },
    permissions: { type: Object, default: () => ({ isAdmin: false, managedDomain: null }) },
    event:       { type: Object, default: () => ({ code: 'EVT' }) },
});

const domainList = ref([...props.domains]);

const STATUS_COLORS = {
    success: { bg: '#dcfce7', fg: '#166534' },
    secondary: { bg: '#efece4', fg: '#3d3833' },
    danger: { bg: '#fee2e2', fg: '#991b1b' },
    warning: { bg: '#fef3c7', fg: '#92400e' },
    info: { bg: '#dbeafe', fg: '#1e3a8a' },
    primary: { bg: '#dbeafe', fg: '#1e3a8a' },
};
function statusMeta(colorKey) { return STATUS_COLORS[colorKey] || STATUS_COLORS.secondary; }
function defaultStatusId() { return props.statuses.find(s => s.name === 'active')?.id ?? props.statuses[0]?.id ?? ''; }

const q = ref('');
const rows = computed(() => {
    let r = domainList.value.slice();
    if (q.value) {
        const k = q.value.toLowerCase();
        r = r.filter(d => d.label.toLowerCase().includes(k) || d.code.toLowerCase().includes(k));
    }
    return r.sort((a, b) => a.sortOrder - b.sortOrder);
});

// ── Add / edit domain modal ───────────────────────────────────────────────────
const showAdd = ref(false);
const saving = ref(false);
const error = ref(null);
const justAdded = ref(null);
const editingId = ref(null);

function freshForm() {
    return { code: '', label: '', color: '#1d4ed8', chip: '#dbeafe', description: '', sortOrder: domainList.value.length + 1, status: defaultStatusId() };
}
const form = ref(freshForm());
const formValid = computed(() => /^[A-Z0-9_]+$/.test(form.value.code) && form.value.label.trim() && form.value.color && form.value.chip);

function openAdd() {
    editingId.value = null;
    form.value = freshForm();
    error.value = null;
    showAdd.value = true;
}
function openEdit(domain) {
    editingId.value = domain.id;
    form.value = {
        code: domain.code,
        label: domain.label,
        color: domain.color,
        chip: domain.chip,
        description: domain.desc || domain.description || '',
        sortOrder: domain.sortOrder,
        status: domain.statusId,
    };
    error.value = null;
    showAdd.value = true;
}
function closeAdd() { showAdd.value = false; }

async function submitDomain() {
    if (!formValid.value || saving.value) return;
    saving.value = true;
    error.value = null;
    const payload = {
        code: form.value.code.trim().toUpperCase(),
        label: form.value.label.trim(),
        color: form.value.color,
        chip: form.value.chip,
        description: form.value.description.trim() || null,
        sort_order: +form.value.sortOrder || 0,
        status_id: form.value.status,
    };
    try {
        if (editingId.value) {
            const { data } = await axios.put(route('mp.domains.update', editingId.value), payload);
            const idx = domainList.value.findIndex(d => d.id === editingId.value);
            if (idx !== -1) domainList.value[idx] = data;
        } else {
            const { data } = await axios.post(route('mp.domains.store'), payload);
            domainList.value.push(data);
            justAdded.value = data.id;
            setTimeout(() => { justAdded.value = null; }, 2400);
        }
        closeAdd();
    } catch (e) {
        error.value = e.response?.status === 403
            ? `You don't have permission to ${editingId.value ? 'edit' : 'add'} a domain.`
            : (e.response?.data?.errors?.code?.[0] ?? 'Could not save this domain. Please try again.');
    } finally {
        saving.value = false;
    }
}

// ── Delete domain ──────────────────────────────────────────────────────────────
const confirmDeleteRow = ref(null);
const deleting = ref(false);
const deleteError = ref(null);
function askDelete(domain) {
    confirmDeleteRow.value = domain;
    deleteError.value = null;
}
async function confirmDelete() {
    if (!confirmDeleteRow.value) return;
    deleting.value = true;
    deleteError.value = null;
    try {
        await axios.delete(route('mp.domains.destroy', confirmDeleteRow.value.id));
        domainList.value = domainList.value.filter(d => d.id !== confirmDeleteRow.value.id);
        confirmDeleteRow.value = null;
    } catch (e) {
        deleteError.value = e.response?.status === 403
            ? "You don't have permission to remove domains."
            : 'Could not remove this domain — it may still have item groups assigned.';
    } finally {
        deleting.value = false;
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
                <h1 class="mp-page-title">Domains</h1>
                <p class="mp-page-sub">{{ domainList.length }} material domains · top-level categories item groups belong to</p>
            </div>
            <div class="mp-head-actions">
                <button v-if="permissions.isAdmin" class="mp-btn mp-btn-primary" @click="openAdd">+ New domain</button>
            </div>
        </div>

        <div class="filterbar">
            <div class="fb-search">
                <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5" stroke-linecap="round"/></svg>
                <input v-model="q" placeholder="Find a domain by name or code…"/>
            </div>
        </div>

        <div class="mp-card mp-card-flush">
            <table class="mp-dt">
                <thead>
                    <tr>
                        <th>Domain</th>
                        <th>Code</th>
                        <th>Description</th>
                        <th>Status</th>
                        <th class="ta-r">Item groups</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="d in rows" :key="d.id" :class="{ 'area-row-new': justAdded === d.id }">
                        <td class="area-name">
                            <span class="domain-chip" :style="{ background: d.chip, color: d.color }">
                                <span class="domain-dot" :style="{ background: d.color }"></span>{{ d.label }}
                            </span>
                        </td>
                        <td class="mono">{{ d.code }}</td>
                        <td class="area-desc">{{ d.desc || d.description || '—' }}</td>
                        <td><span class="status-chip" :style="{ background: statusMeta(d.statusColor).bg, color: statusMeta(d.statusColor).fg }">{{ d.statusName || '—' }}</span></td>
                        <td class="ta-r mono">{{ d.itemGroupsCount }}</td>
                        <td class="ta-r mp-dt-actions">
                            <button v-if="permissions.isAdmin" class="mp-icon-btn mp-icon-edit" title="Edit" @click="openEdit(d)"><i class="bx bx-pencil"></i></button>
                            <button v-if="permissions.isAdmin" class="mp-icon-btn mp-icon-del" title="Delete" @click="askDelete(d)"><i class="bx bx-trash"></i></button>
                        </td>
                    </tr>
                    <tr v-if="!rows.length">
                        <td colspan="6" class="area-empty">No domains match this search.</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="dt-foot">Showing <b>{{ rows.length }}</b> of {{ domainList.length }} domains</div>
    </div>

    <!-- ── New domain modal ──────────────────────────────────────────────── -->
    <Teleport to="body" v-if="showAdd">
        <div class="skum-scrim" @click.self="closeAdd">
            <div class="skum" role="dialog" aria-modal="true">
                <header class="skum-hd">
                    <div class="skum-hd-l">
                        <div class="skum-hd-tag"><span class="mono">{{ event.code }}</span><span>·</span><span>Domains</span></div>
                        <h2 class="skum-title">{{ editingId ? 'Edit domain' : 'New domain' }}</h2>
                        <p class="skum-sub">A top-level material category (Overlay, Power, IT…) that item groups belong to.</p>
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
                                <input v-model="form.code" placeholder="e.g. OVR" class="mono" :disabled="!!editingId"/>
                                <span class="field-hint">{{ editingId ? 'Code cannot be changed after creation.' : 'Letters, numbers, underscores only.' }}</span>
                            </div>
                            <div class="field">
                                <label class="field-lbl">Sort order</label>
                                <input type="number" min="0" v-model="form.sortOrder"/>
                            </div>
                            <div class="field" style="grid-column: span 2">
                                <label class="field-lbl">Label</label>
                                <input v-model="form.label" placeholder="e.g. Overlay"/>
                            </div>
                            <div class="field">
                                <label class="field-lbl">Color</label>
                                <div class="color-field">
                                    <input type="color" v-model="form.color"/>
                                    <input v-model="form.color" class="mono"/>
                                </div>
                            </div>
                            <div class="field">
                                <label class="field-lbl">Chip background</label>
                                <div class="color-field">
                                    <input type="color" v-model="form.chip"/>
                                    <input v-model="form.chip" class="mono"/>
                                </div>
                            </div>
                            <div class="field" style="grid-column: span 2">
                                <label class="field-lbl">Description</label>
                                <input v-model="form.description" placeholder="optional"/>
                            </div>
                            <div class="field">
                                <label class="field-lbl">Status</label>
                                <div class="sel">
                                    <select v-model="form.status">
                                        <option v-for="s in statuses" :key="s.id" :value="s.id">{{ s.name }}</option>
                                    </select>
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6"/></svg>
                                </div>
                            </div>
                            <div class="field" style="grid-column: span 2">
                                <label class="field-lbl">Preview</label>
                                <span class="domain-chip" :style="{ background: form.chip, color: form.color }">
                                    <span class="domain-dot" :style="{ background: form.color }"></span>{{ form.label || 'Label' }}
                                </span>
                            </div>
                        </div>
                    </section>
                </div>

                <footer class="skum-ft">
                    <div class="skum-ft-l">
                        <span v-if="error" class="skum-ft-warn"><span class="skum-ft-dot" style="background:#b45309"></span>{{ error }}</span>
                        <span v-else-if="formValid" class="skum-ft-ok"><span class="skum-ft-dot" style="background:#16a34a"></span>Ready to add</span>
                        <span v-else class="skum-ft-warn"><span class="skum-ft-dot" style="background:#b45309"></span>Code and label are required</span>
                    </div>
                    <div class="skum-ft-r">
                        <button class="mp-btn" @click="closeAdd">Cancel</button>
                        <button class="mp-btn mp-btn-primary" :disabled="!formValid || saving" @click="submitDomain">
                            {{ saving ? (editingId ? 'Saving…' : 'Adding…') : (editingId ? 'Save changes' : 'Add domain') }}
                        </button>
                    </div>
                </footer>
            </div>
        </div>
    </Teleport>

    <ConfirmModal
        v-if="confirmDeleteRow"
        :title="`Remove ${confirmDeleteRow.label}?`"
        message="Item groups assigned to it will block this until reassigned."
        confirm-text="Remove"
        loading-text="Removing…"
        :loading="deleting"
        danger
        @cancel="confirmDeleteRow = null"
        @confirm="confirmDelete"
    >
        <p v-if="deleteError" class="cfm-err">{{ deleteError }}</p>
    </ConfirmModal>
</template>

<style scoped>
.mp-page { max-width: 100%; }
.mp-page-head { display: flex; justify-content: space-between; align-items: flex-end; gap: 24px; margin-bottom: 20px; }
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

.filterbar { display: flex; align-items: center; gap: 8px; margin-bottom: 12px; }
.fb-search {
    flex: 1; display: flex; align-items: center; gap: 8px;
    background: #fff; border: 1px solid #e8e4db; border-radius: 6px; padding: 6px 10px; color: #76706a;
}
.fb-search input { border: 0; outline: none; background: transparent; flex: 1; font-size: 12.5px; color: #1a1614; }

.mp-dt { width: 100%; border-collapse: collapse; font-size: 13px; }
.mp-dt th {
    background: #fbfaf6; border-bottom: 1px solid #e8e4db;
    color: #76706a; font-size: 11px; text-transform: uppercase; letter-spacing: .05em;
    padding: 10px 14px; text-align: left; white-space: nowrap;
}
.mp-dt td { padding: 11px 14px; border-bottom: 1px solid #f3f0ea; vertical-align: middle; color: #1a1614; }
.mp-dt tr:last-child td { border-bottom: none; }
.area-name { font-weight: 500; }
.area-desc { font-size: 12.5px; color: #76706a; }
.area-empty { text-align: center; padding: 24px; color: #76706a; }

.domain-chip { display: inline-flex; align-items: center; gap: 6px; font-size: 12.5px; font-weight: 600; padding: 3px 10px 3px 8px; border-radius: 20px; }
.domain-dot { width: 7px; height: 7px; border-radius: 50%; flex-shrink: 0; }
.status-chip { display: inline-flex; align-items: center; font-size: 12px; font-weight: 600; padding: 2px 9px; border-radius: 20px; }

@keyframes area-newrow { 0%, 100% { background: transparent; } 20% { background: rgba(15,118,110,.12); } }
.area-row-new td { animation: area-newrow 2.4s ease; }

.dt-foot { font-size: 12px; color: #76706a; text-align: right; margin: 8px 0 16px; }
.mono { font-family: ui-monospace, 'SF Mono', Menlo, monospace; }
.ta-r { text-align: right; }

.mp-dt-actions { display: flex; gap: 4px; justify-content: flex-end; white-space: nowrap; }
.mp-icon-btn { width: 30px; height: 30px; border-radius: 6px; border: 1px solid transparent; display: inline-flex; align-items: center; justify-content: center; cursor: pointer; font-size: 14px; transition: background .15s; }
.mp-icon-edit { background: #fff7e6; border-color: #fde7b0; color: #d97706; }
.mp-icon-edit:hover { background: #fef3c7; }
.mp-icon-del { background: #fff1f2; border-color: #fecdd3; color: #dc2626; }
.mp-icon-del:hover { background: #ffe4e6; }
.cfm-err { font-size: 12.5px; color: #991b1b; margin-top: 8px; }

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
    width: 100%; max-width: 520px;
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
.field input:disabled { background: #f6f5f1; color: #76706a; cursor: not-allowed; }

.color-field { display: flex; align-items: center; gap: 8px; }
.color-field input[type="color"] { width: 36px; height: 34px; padding: 2px; border: 1px solid #e8e4db; border-radius: 7px; cursor: pointer; flex-shrink: 0; }
.color-field input[type="text"], .color-field input:not([type="color"]) { flex: 1; }
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
