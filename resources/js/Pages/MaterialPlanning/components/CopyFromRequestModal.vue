<script setup>
import { ref, computed } from 'vue';
import axios from 'axios';
import FormModal from './FormModal.vue';

const props = defineProps({
    requests:    { type: Array, default: () => [] },
    catalog:     { type: Array, default: () => [] },
    excludeCode: { type: String, default: null },
});

const emit = defineEmits(['close', 'copy']);

const search = ref('');
const loadingCode = ref(null);
const error = ref(null);

const candidates = computed(() => {
    let list = props.requests.filter(r => r.id !== props.excludeCode && r.items > 0);
    const q = search.value.trim().toLowerCase();
    if (q) {
        list = list.filter(r =>
            r.id.toLowerCase().includes(q) ||
            r.title?.toLowerCase().includes(q) ||
            r.venue?.toLowerCase().includes(q)
        );
    }
    return list.slice(0, 50);
});

function fmtMoney(n) { return '$' + Number(n || 0).toLocaleString('en-US'); }

async function pickRequest(r) {
    if (loadingCode.value) return;
    loadingCode.value = r.id;
    error.value = null;
    try {
        const { data } = await axios.get(route('mp.requests.show', r.id));
        const lines = (data.lines || []).map(line => {
            const catalogItem = props.catalog.find(c => c.sku === line.sku);
            return {
                domain: catalogItem?.domain || line.domain || 'IT',
                group: catalogItem?.group || '',
                sub: catalogItem?.sub || '',
                sku: line.sku,
                qty: line.qty,
                comment: '',
            };
        });
        emit('copy', lines);
    } catch (e) {
        error.value = 'Could not load that request. Please try again.';
    } finally {
        loadingCode.value = null;
    }
}

function close() { emit('close'); }
</script>

<template>
    <FormModal
        max-width="560px"
        title="Copy from previous request"
        subtitle="Pick a request to copy its line items from — they'll be added to what you already have here."
        @close="close"
    >
        <template #eyebrow>
            <span>Copy items</span>
        </template>

        <div class="cfr-body">
            <input v-model="search" class="cfr-search" placeholder="Search by code, title or venue…"/>

            <p v-if="error" class="cfr-err">{{ error }}</p>

            <div v-if="!candidates.length" class="cfr-empty">No requests match this search.</div>
            <ul v-else class="cfr-list">
                <li v-for="r in candidates" :key="r.id" class="cfr-row" @click="pickRequest(r)">
                    <div class="cfr-row-main">
                        <div class="cfr-row-head">
                            <span class="mono cfr-code">{{ r.id }}</span>
                            <span class="cfr-title">{{ r.title }}</span>
                        </div>
                        <div class="cfr-row-meta">
                            <span>{{ r.venue }}</span>
                            <span>·</span>
                            <span>{{ r.items }} item{{ r.items === 1 ? '' : 's' }}</span>
                            <span>·</span>
                            <span class="mono">{{ fmtMoney(r.value) }}</span>
                            <span>·</span>
                            <span>updated {{ r.updated }}</span>
                        </div>
                    </div>
                    <button class="mp-btn mp-btn-sm" :disabled="loadingCode === r.id" @click.stop="pickRequest(r)">
                        {{ loadingCode === r.id ? 'Copying…' : 'Copy' }}
                    </button>
                </li>
            </ul>
        </div>
    </FormModal>
</template>

<style scoped>
.mp-btn {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 7px 12px; border-radius: 6px;
    border: 1px solid #e8e4db; background: #fff;
    font-size: 12.5px; font-weight: 500; color: #1a1614; cursor: pointer; transition: background .12s, border-color .12s;
}
.mp-btn:hover { background: #fbfaf6; border-color: #3d3833; }
.mp-btn:disabled { opacity: .5; cursor: not-allowed; }
.mp-btn-sm { padding: 5px 10px; font-size: 12px; flex-shrink: 0; }

.mono { font-family: ui-monospace, 'SF Mono', Menlo, monospace; }

.cfr-body { padding: 18px 0; }

.cfr-search {
    width: 100%; border: 1px solid #e8e4db; border-radius: 7px;
    padding: 8px 12px; font-size: 13px; color: #1a1614; background: #fff;
    outline: none; transition: border-color .12s; margin-bottom: 14px; box-sizing: border-box;
}
.cfr-search:focus { border-color: #0f766e; box-shadow: 0 0 0 3px rgba(15,118,110,.1); }

.cfr-err { font-size: 12.5px; color: #991b1b; margin: 0 0 12px; }
.cfr-empty { text-align: center; padding: 24px 8px; color: #76706a; font-size: 13px; }

.cfr-list { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 6px; }
.cfr-row {
    display: flex; align-items: center; justify-content: space-between; gap: 12px;
    border: 1px solid #e8e4db; border-radius: 8px;
    padding: 10px 12px; cursor: pointer; transition: background .12s, border-color .12s;
}
.cfr-row:hover { border-color: #a39d96; background: #fbfaf6; }
.cfr-row-main { min-width: 0; flex: 1; }
.cfr-row-head { display: flex; align-items: center; gap: 8px; }
.cfr-code { font-size: 11.5px; color: #0f766e; font-weight: 600; flex-shrink: 0; }
.cfr-title { font-size: 13px; font-weight: 600; color: #1a1614; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.cfr-row-meta { display: flex; gap: 6px; flex-wrap: wrap; font-size: 11.5px; color: #76706a; margin-top: 3px; }
</style>
