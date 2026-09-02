<script setup>
import { ref, computed } from 'vue';
import axios from 'axios';
import ConfirmModal from '../components/ConfirmModal.vue';
import FormModal from '../components/FormModal.vue';
import ProgressButton from '../components/ProgressButton.vue';

const props = defineProps({
    itemGroups:  Array,
    domains:     Array,
    statuses:    { type: Array, default: () => [] },
    permissions: { type: Object, default: () => ({ isAdmin: false, managedDomain: null }) },
    event:       { type: Object, default: () => ({ code: 'EVT' }) },
});

const groupList = ref([...props.itemGroups]);
const eventGroups = computed(() => props.event?.id ? groupList.value.filter(g => g.eventId === props.event.id) : groupList.value);
const eventDomains = computed(() => props.event?.id ? props.domains.filter(d => d.eventId === props.event.id) : props.domains);

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
const fDomain = ref('all');
const rows = computed(() => {
    let r = eventGroups.value.slice();
    if (fDomain.value !== 'all') r = r.filter(g => g.domain === fDomain.value);
    if (q.value) {
        const k = q.value.toLowerCase();
        r = r.filter(g => g.label.toLowerCase().includes(k) || g.code.toLowerCase().includes(k));
    }
    return r.sort((a, b) => a.sortOrder - b.sortOrder);
});

function domainLabel(code) { return eventDomains.value.find(d => d.id === code)?.label || code; }

// ── Add / edit item group modal ───────────────────────────────────────────────
const showAdd = ref(false);
const saving = ref(false);
const error = ref(null);
const justAdded = ref(null);
const editingId = ref(null);

function freshForm() {
    return { code: '', domain: eventDomains.value[0]?.id ?? '', label: '', description: '', sortOrder: eventGroups.value.length + 1, status: defaultStatusId() };
}
const form = ref(freshForm());
const formValid = computed(() => /^[A-Z0-9_]+$/.test(form.value.code) && form.value.domain && form.value.label.trim());

function openAdd() {
    editingId.value = null;
    form.value = freshForm();
    error.value = null;
    showAdd.value = true;
}
function openEdit(group) {
    editingId.value = group.id;
    form.value = {
        code: group.code,
        domain: group.domain,
        label: group.label,
        description: group.description || '',
        sortOrder: group.sortOrder,
        status: group.statusId,
    };
    error.value = null;
    showAdd.value = true;
}
function closeAdd() { showAdd.value = false; }

async function submitGroup() {
    if (!formValid.value || saving.value) return;
    saving.value = true;
    error.value = null;
    const payload = {
        code: form.value.code.trim().toUpperCase(),
        domain_id: form.value.domain,
        label: form.value.label.trim(),
        description: form.value.description.trim() || null,
        sort_order: +form.value.sortOrder || 0,
        status_id: form.value.status,
    };
    if (!editingId.value) payload.event_id = props.event.id;
    try {
        if (editingId.value) {
            const { data } = await axios.put(route('mp.item-groups.update', editingId.value), payload);
            const idx = groupList.value.findIndex(g => g.id === editingId.value);
            if (idx !== -1) groupList.value[idx] = data;
        } else {
            const { data } = await axios.post(route('mp.item-groups.store'), payload);
            groupList.value.push(data);
            justAdded.value = data.id;
            setTimeout(() => { justAdded.value = null; }, 2400);
        }
        closeAdd();
    } catch (e) {
        error.value = e.response?.status === 403
            ? `You don't have permission to ${editingId.value ? 'edit' : 'add'} an item group.`
            : (e.response?.data?.errors?.code?.[0] ?? 'Could not save this item group. Please try again.');
    } finally {
        saving.value = false;
    }
}

// ── Delete item group ────────────────────────────────────────────────────────
const confirmDeleteRow = ref(null);
const deleting = ref(false);
const deleteError = ref(null);
function askDelete(group) {
    confirmDeleteRow.value = group;
    deleteError.value = null;
}
async function confirmDelete() {
    if (!confirmDeleteRow.value) return;
    deleting.value = true;
    deleteError.value = null;
    try {
        await axios.delete(route('mp.item-groups.destroy', confirmDeleteRow.value.id));
        groupList.value = groupList.value.filter(g => g.id !== confirmDeleteRow.value.id);
        confirmDeleteRow.value = null;
    } catch (e) {
        deleteError.value = e.response?.status === 403
            ? "You don't have permission to remove item groups."
            : 'Could not remove this item group — it may still have subgroups assigned.';
    } finally {
        deleting.value = false;
    }
}

</script>

<template>
    <div class="mp-page">
        <div class="mp-page-head">
            <div>
                <h1 class="mp-page-title">Item Groups</h1>
                <p class="mp-page-sub">{{ eventGroups.length }} item groups · organizational groupings that item subgroups belong to</p>
            </div>
            <div class="mp-head-actions">
                <button v-if="permissions.isAdmin" class="mp-btn mp-btn-primary" @click="openAdd">+ New item group</button>
            </div>
        </div>

        <div class="filterbar">
            <div class="fb-search">
                <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5" stroke-linecap="round"/></svg>
                <input v-model="q" placeholder="Find an item group by name or code…"/>
            </div>
            <div class="fb-sel">
                <label>Domain</label>
                <select v-model="fDomain">
                    <option value="all">All domains</option>
                    <option v-for="d in eventDomains" :key="d.id" :value="d.id">{{ d.label }}</option>
                </select>
            </div>
        </div>

        <div class="mp-card mp-card-flush">
            <table class="mp-dt">
                <thead>
                    <tr>
                        <th>Item group</th>
                        <th>Code</th>
                        <th>Domain</th>
                        <th>Description</th>
                        <th>Status</th>
                        <th class="ta-r">Subgroups</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="g in rows" :key="g.id" :class="{ 'area-row-new': justAdded === g.id }">
                        <td class="area-name">{{ g.label }}</td>
                        <td class="mono">{{ g.code }}</td>
                        <td><span class="area-domain">{{ g.domainLabel || domainLabel(g.domain) }}</span></td>
                        <td class="area-desc">{{ g.description || '—' }}</td>
                        <td><span class="status-chip" :style="{ background: statusMeta(g.statusColor).bg, color: statusMeta(g.statusColor).fg }">{{ g.statusName || '—' }}</span></td>
                        <td class="ta-r mono">{{ g.subgroupsCount }}</td>
                        <td class="ta-r mp-dt-actions">
                            <button v-if="permissions.isAdmin" class="mp-icon-btn mp-icon-edit" title="Edit" @click="openEdit(g)"><i class="bx bx-pencil"></i></button>
                            <button v-if="permissions.isAdmin" class="mp-icon-btn mp-icon-del" title="Delete" @click="askDelete(g)"><i class="bx bx-trash"></i></button>
                        </td>
                    </tr>
                    <tr v-if="!rows.length">
                        <td colspan="7" class="area-empty">No item groups match these filters.</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="dt-foot">Showing <b>{{ rows.length }}</b> of {{ eventGroups.length }} item groups</div>
    </div>

    <!-- ── New item group modal ──────────────────────────────────────────── -->
    <FormModal
        :show="showAdd"
        max-width="520px"
        :title="editingId ? 'Edit item group' : 'New item group'"
        subtitle="A broad catalog grouping that item subgroups belong to."
        @close="closeAdd"
    >
        <template #eyebrow>
            <span class="mono">{{ event.code }}</span><span>·</span><span>Item Groups</span>
        </template>

                    <section class="skum-sec">
                        <div class="form-grid">
                            <div class="field">
                                <label class="field-lbl">Code</label>
                                <input v-model="form.code" placeholder="e.g. FURN" class="mono" :disabled="!!editingId"/>
                                <span class="field-hint">{{ editingId ? 'Code cannot be changed after creation.' : 'Letters, numbers, underscores only.' }}</span>
                            </div>
                            <div class="field">
                                <label class="field-lbl">Domain</label>
                                <div class="sel">
                                    <select v-model="form.domain">
                                        <option v-for="d in eventDomains" :key="d.id" :value="d.id">{{ d.label }}</option>
                                    </select>
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6"/></svg>
                                </div>
                            </div>
                            <div class="field">
                                <label class="field-lbl">Sort order</label>
                                <input type="number" min="0" v-model="form.sortOrder"/>
                            </div>
                            <div class="field" style="grid-column: span 2">
                                <label class="field-lbl">Label</label>
                                <input v-model="form.label" placeholder="e.g. Furniture"/>
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
                        </div>
                    </section>

        <template #footer-left>
            <span v-if="error" class="skum-ft-warn"><span class="skum-ft-dot" style="background:#b45309"></span>{{ error }}</span>
            <span v-else-if="formValid" class="skum-ft-ok"><span class="skum-ft-dot" style="background:#16a34a"></span>Ready to add</span>
            <span v-else class="skum-ft-warn"><span class="skum-ft-dot" style="background:#b45309"></span>Code, domain and label are required</span>
        </template>
        <template #footer-actions>
            <ProgressButton
                variant="primary"
                :disabled="!formValid"
                :loading="saving"
                :text="editingId ? 'Save changes' : 'Add item group'"
                :loading-text="editingId ? 'Saving…' : 'Adding…'"
                @click="submitGroup"
            />
        </template>
    </FormModal>

    <ConfirmModal
        v-if="confirmDeleteRow"
        :title="`Remove ${confirmDeleteRow.label}?`"
        message="Subgroups assigned to it will block this until reassigned."
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
.fb-sel { display: flex; flex-direction: column; gap: 3px; }
.fb-sel label { font-size: 10.5px; color: #76706a; text-transform: uppercase; letter-spacing: .06em; padding-left: 2px; }
.fb-sel select {
    appearance: none;
    background: #fff url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24'%3E%3Cpath fill='none' stroke='%2376706a' stroke-width='2' stroke-linecap='round' stroke-linejoin='round' d='M6 9l6 6 6-6'/%3E%3C/svg%3E") no-repeat right 8px center;
    border: 1px solid #e8e4db; border-radius: 6px;
    padding: 6px 24px 6px 10px; font-size: 12.5px; color: #1a1614; cursor: pointer; outline: none;
}

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
.area-domain { font-size: 12px; color: #3d3833; background: #f6f5f1; padding: 2px 8px; border-radius: 5px; }
.area-empty { text-align: center; padding: 24px; color: #76706a; }
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
.sel { position: relative; display: flex; align-items: center; }
.sel select {
    width: 100%; appearance: none; border: 1px solid #e8e4db; border-radius: 7px;
    padding: 8px 30px 8px 11px; font-size: 13px; color: #1a1614; background: #fff;
    outline: none; cursor: pointer; transition: border-color .12s;
}
.sel select:focus { border-color: #0f766e; box-shadow: 0 0 0 3px rgba(15,118,110,.1); }
.sel svg { position: absolute; right: 10px; pointer-events: none; color: #76706a; }

.skum-ft-ok, .skum-ft-warn { display: flex; align-items: center; gap: 5px; font-size: 12px; }
.skum-ft-ok   { color: #16a34a; }
.skum-ft-warn { color: #b45309; }
.skum-ft-dot  { width: 6px; height: 6px; border-radius: 50%; flex-shrink: 0; }
</style>
