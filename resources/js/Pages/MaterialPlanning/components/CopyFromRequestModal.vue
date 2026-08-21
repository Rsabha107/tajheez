<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import axios from 'axios';

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
function onEsc(e) { if (e.key === 'Escape') close(); }
onMounted(()   => document.addEventListener('keydown', onEsc));
onUnmounted(() => document.removeEventListener('keydown', onEsc));
</script>

<template>
    <Teleport to="body">
        <div class="skum-scrim" @click.self="close">
            <div class="skum" role="dialog" aria-modal="true">
                <header class="skum-hd">
                    <div class="skum-hd-l">
                        <div class="skum-hd-tag"><span>Copy items</span></div>
                        <h2 class="skum-title">Copy from previous request</h2>
                        <p class="skum-sub">Pick a request to copy its line items from — they'll be added to what you already have here.</p>
                    </div>
                    <button class="skum-x" @click="close" aria-label="Close">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18"/></svg>
                    </button>
                </header>

                <div class="skum-body">
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

                <footer class="skum-ft">
                    <div class="skum-ft-l"></div>
                    <div class="skum-ft-r">
                        <button class="mp-btn" @click="close">Cancel</button>
                    </div>
                </footer>
            </div>
        </div>
    </Teleport>
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
.skum-body { padding: 18px 24px; overflow-y: auto; max-height: 56vh; }

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

.skum-ft {
    display: flex; align-items: center; justify-content: space-between;
    gap: 12px; padding: 16px 24px; border-top: 1px solid #e8e4db;
    background: #fbfaf6; border-radius: 0 0 13px 13px;
}
.skum-ft-r { display: flex; gap: 8px; flex-shrink: 0; }
</style>
