<script setup>
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';
import Swal from 'sweetalert2';
import FaFormModal from '@/Components/ems/FaFormModal.vue';

const rows         = ref([]);
const loading      = ref(false);
const search       = ref('');
const statusFilter = ref('all');
const refreshing   = ref(false);
const showModal    = ref(false);
const modalMode    = ref('create');
const selectedRow  = ref(null);

const statusOptions = computed(() => {
    const seen = new Map();
    rows.value.forEach(r => { if (r.status_name && !seen.has(r.status_color)) seen.set(r.status_color, r.status_name); });
    return [...seen.entries()].map(([color, name]) => ({ color, name }));
});

const filtered = computed(() => {
    let list = rows.value;
    if (statusFilter.value !== 'all') list = list.filter(r => r.status_color === statusFilter.value);
    const q = search.value.toLowerCase();
    if (q) list = list.filter(r => r.title?.toLowerCase().includes(q));
    return list;
});

const evStatusStyle = (color) => ({
    success:   { bg: '#dcfce7', fg: '#16a34a', dot: '#16a34a' },
    danger:    { bg: '#fee2e2', fg: '#dc2626', dot: '#dc2626' },
    warning:   { bg: '#fef3c7', fg: '#b45309', dot: '#b45309' },
    info:      { bg: '#dbeafe', fg: '#1d4ed8', dot: '#1d4ed8' },
    secondary: { bg: '#f1f5f9', fg: '#64748b', dot: '#94a3b8' },
}[color] || { bg: '#f1f5f9', fg: '#64748b', dot: '#94a3b8' });

async function fetchData() {
    loading.value = true;
    try {
        const { data } = await axios.get(route('functional-areas.data'), { params: { limit: 200, offset: 0, sort: 'id', order: 'desc' } });
        rows.value = data.rows ?? data ?? [];
    } catch (_) {}
    loading.value = false;
}

async function refreshTable() {
    if (refreshing.value) return;
    refreshing.value = true;
    await fetchData();
    setTimeout(() => { refreshing.value = false; }, 700);
}

function openCreate() { modalMode.value = 'create'; selectedRow.value = null; showModal.value = true; }
function openEdit(row) { modalMode.value = 'edit'; selectedRow.value = row; showModal.value = true; }
function closeModal() { showModal.value = false; selectedRow.value = null; }

async function deleteRow(row) {
    const result = await Swal.fire({ title: 'Delete functional area?', text: `"${row.title}"`, icon: 'warning', showCancelButton: true, confirmButtonText: 'Yes, delete', confirmButtonColor: '#dc2626' });
    if (!result.isConfirmed) return;
    try { await axios.delete(route('functional-areas.destroy', row.id)); fetchData(); } catch (_) {}
}

onMounted(fetchData);
</script>

<template>
    <div class="mp-page">
        <div class="mp-page-head">
            <div>
                <h1 class="mp-page-title">Functional Areas</h1>
                <p class="mp-page-sub">All functional areas in the system. Click a row to edit.</p>
            </div>
            <div class="mp-head-actions">
                <button class="mp-btn mp-btn-icon" title="Refresh table" @click="refreshTable">
                    <svg :class="{ spin: refreshing }" viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M23 4v6h-6"/><path d="M1 20v-6h6"/>
                        <path d="M3.51 9a9 9 0 0114.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0020.49 15"/>
                    </svg>
                </button>
                <button class="mp-btn mp-btn-primary" @click="openCreate">+ Add functional area</button>
            </div>
        </div>

        <div class="mp-filterbar">
            <div class="mp-fb-search">
                <i class="bx bx-search"></i>
                <input v-model="search" placeholder="Search functional areas…" />
            </div>
            <div class="mp-fb-sel">
                <label>Status</label>
                <select v-model="statusFilter">
                    <option value="all">All</option>
                    <option v-for="s in statusOptions" :key="s.color" :value="s.color">{{ s.name }}</option>
                </select>
            </div>
        </div>

        <div class="mp-card mp-card-flush">
            <div v-if="loading" class="mp-dt-empty">Loading…</div>
            <table v-else class="mp-dt">
                <thead>
                    <tr><th>Title</th><th>Status</th><th>Updated</th><th></th></tr>
                </thead>
                <tbody>
                    <tr v-if="!filtered.length"><td colspan="4" class="mp-dt-empty">No functional areas found.</td></tr>
                    <tr v-for="row in filtered" :key="row.id" class="mp-dt-row" @click="openEdit(row)">
                        <td class="mp-dt-title">{{ row.title }}</td>
                        <td>
                            <span v-if="row.status_name" class="mp-pill"
                                :style="{ background: evStatusStyle(row.status_color).bg, color: evStatusStyle(row.status_color).fg }">
                                <span class="mp-pill-dot" :style="{ background: evStatusStyle(row.status_color).dot }" />
                                {{ row.status_name }}
                            </span>
                            <span v-else class="mp-muted">—</span>
                        </td>
                        <td class="mp-dt-when">{{ row.updated_at ?? '—' }}</td>
                        <td class="mp-dt-actions" @click.stop>
                            <button class="mp-icon-btn mp-icon-edit" title="Edit" @click="openEdit(row)"><i class="bx bx-pencil"></i></button>
                            <button class="mp-icon-btn mp-icon-del" title="Delete" @click="deleteRow(row)"><i class="bx bx-trash"></i></button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="mp-dt-foot">Showing <b>{{ filtered.length }}</b> of {{ rows.length }} functional areas</div>

        <FaFormModal :show="showModal" :mode="modalMode" :fa="selectedRow"
            @close="closeModal" @saved="() => { closeModal(); fetchData(); }" />
    </div>
</template>

<style scoped>
.mp-page { max-width: 100%; }
.mp-page-head { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px; background: #fbfaf6; border: 1px solid #e8e4db; border-radius: 8px; padding: 14px 18px; }
.mp-page-title { font-size: 20px; font-weight: 700; color: #1a1614; margin: 0; }
.mp-page-sub { font-size: 13px; color: #76706a; margin: 2px 0 0; }
.mp-head-actions { display: flex; gap: 8px; align-items: center; flex-shrink: 0; }
.mp-btn { display: inline-flex; align-items: center; gap: 5px; padding: 7px 14px; border-radius: 7px; border: 1px solid #e8e4db; background: #fff; font-size: 13px; color: #1a1614; cursor: pointer; transition: background .15s; }
.mp-btn:hover { background: #f6f5f1; }
.mp-btn-primary { background: #0f766e; border-color: #0f766e; color: #fff; }
.mp-btn-primary:hover { background: #0d9488; }
.mp-btn-icon { padding: 7px 9px; color: #76706a; }
.mp-btn-icon:hover { color: #1a1614; }
.mp-filterbar { display: flex; align-items: center; gap: 12px; margin-bottom: 12px; flex-wrap: wrap; }
.mp-fb-search { display: flex; align-items: center; gap: 7px; background: #fff; border: 1px solid #e8e4db; border-radius: 7px; padding: 7px 12px; flex: 1; min-width: 220px; }
.mp-fb-search input { border: none; outline: none; font-size: 13px; background: transparent; flex: 1; }
.mp-fb-sel { display: flex; align-items: center; gap: 6px; font-size: 12px; color: #76706a; }
.mp-fb-sel select { border: 1px solid #e8e4db; border-radius: 6px; padding: 5px 8px; font-size: 12px; background: #fff; }
.mp-card { background: #fff; border: 1px solid #e8e4db; border-radius: 10px; margin-bottom: 14px; }
.mp-card-flush { padding: 0; overflow: hidden; }
.mp-dt { width: 100%; border-collapse: collapse; font-size: 13px; }
.mp-dt th { background: #fbfaf6; border-bottom: 1px solid #e8e4db; color: #76706a; font-size: 11px; text-transform: uppercase; letter-spacing: .05em; padding: 10px 14px; text-align: left; white-space: nowrap; }
.mp-dt td { padding: 11px 14px; border-bottom: 1px solid #f3f0ea; vertical-align: middle; color: #1a1614; }
.mp-dt-row { cursor: pointer; transition: background .12s; }
.mp-dt-row:hover td { background: #fbfaf6; }
.mp-dt-title { font-weight: 500; }
.mp-dt-when { font-size: 12px; color: #76706a; }
.mp-dt-foot { font-size: 12px; color: #76706a; text-align: right; margin-top: 8px; }
.mp-dt-empty { padding: 32px; text-align: center; color: #76706a; font-size: 13px; }
.mp-dt-actions { display: flex; gap: 4px; justify-content: flex-end; }
.mp-muted { color: #a39d96; font-size: 12px; }
.mp-pill { display: inline-flex; align-items: center; gap: 5px; padding: 3px 9px; border-radius: 20px; font-size: 12px; font-weight: 600; }
.mp-pill-dot { width: 6px; height: 6px; border-radius: 50%; }
.mp-icon-btn { width: 30px; height: 30px; border-radius: 6px; border: 1px solid transparent; display: inline-flex; align-items: center; justify-content: center; cursor: pointer; font-size: 14px; transition: background .15s; }
.mp-icon-edit { background: #fff7e6; border-color: #fde7b0; color: #d97706; }
.mp-icon-edit:hover { background: #fef3c7; }
.mp-icon-del { background: #fff1f2; border-color: #fecdd3; color: #dc2626; }
.mp-icon-del:hover { background: #ffe4e6; }
@keyframes spin { to { transform: rotate(360deg); } }
.spin { animation: spin .7s linear; }
</style>
