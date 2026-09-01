<script setup>
import { ref, onMounted, onUnmounted } from 'vue';

const props = defineProps({
    // Each item: { key, label, remove() } — built by the consuming view from
    // whichever of its own filter refs are currently not at their default.
    activeFilters: { type: Array, default: () => [] },
});

const emit = defineEmits(['clear-all']);

const open = ref(false);
const wrapRef = ref(null);

function toggle() { open.value = !open.value; }
function close() { open.value = false; }

function onOutsideClick(e) {
    if (open.value && wrapRef.value && !wrapRef.value.contains(e.target)) close();
}
function onEsc(e) { if (e.key === 'Escape') close(); }

onMounted(() => {
    document.addEventListener('click', onOutsideClick);
    document.addEventListener('keydown', onEsc);
});
onUnmounted(() => {
    document.removeEventListener('click', onOutsideClick);
    document.removeEventListener('keydown', onEsc);
});
</script>

<template>
    <div class="fp-wrap" ref="wrapRef">
        <button class="mp-btn fp-trigger" @click="toggle">
            <i class="bx bx-filter-alt"></i> Filters
            <span v-if="activeFilters.length" class="fp-badge">{{ activeFilters.length }}</span>
            <svg class="fp-chev" :class="{ 'fp-chev-open': open }" viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6"/></svg>
        </button>

        <div v-if="open" class="fp-panel">
            <div class="fp-panel-body">
                <slot />
            </div>
            <div class="fp-panel-foot">
                <button class="fp-clear" :disabled="!activeFilters.length" @click="emit('clear-all')">Clear all</button>
                <button class="mp-btn mp-btn-primary mp-btn-sm" @click="close">Done</button>
            </div>
        </div>
    </div>
</template>

<style scoped>
.mp-btn {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 7px 12px; border-radius: 6px;
    border: 1px solid #e8e4db; background: #fff;
    font-size: 12.5px; font-weight: 500; color: #1a1614; cursor: pointer;
    transition: background .12s, border-color .12s;
}
.mp-btn:hover { background: #fbfaf6; border-color: #3d3833; }
.mp-btn-primary { background: #1a1614; border-color: #1a1614; color: #fff; }
.mp-btn-primary:hover { background: #0a0806; border-color: #0a0806; }
.mp-btn-sm { padding: 4px 10px; font-size: 12px; }

.fp-wrap { position: relative; }
.fp-trigger { position: relative; }
.fp-badge {
    display: inline-flex; align-items: center; justify-content: center;
    min-width: 16px; height: 16px; padding: 0 4px; border-radius: 8px;
    background: #0f766e; color: #fff; font-size: 10px; font-weight: 700;
}
.fp-chev { color: #76706a; transition: transform .15s; }
.fp-chev-open { transform: rotate(180deg); }

.fp-panel {
    position: absolute; top: calc(100% + 8px); right: 0; z-index: 50;
    width: 460px; max-width: calc(100vw - 32px); max-height: 70vh; overflow: hidden;
    background: #fff; border: 1px solid #e8e4db; border-radius: 12px;
    box-shadow: 0 12px 32px rgba(26,22,20,.14);
    display: flex; flex-direction: column;
}
.fp-panel-body { padding: 16px; overflow-y: auto; flex: 1; min-height: 0; }
.fp-panel-foot {
    display: flex; align-items: center; justify-content: space-between;
    gap: 10px; padding: 12px 16px; border-top: 1px solid #efece4;
    background: #fbfaf6; border-radius: 0 0 12px 12px; flex-shrink: 0;
}
.fp-clear {
    border: none; background: none; padding: 0;
    font-size: 12.5px; color: #0f766e; font-weight: 500; cursor: pointer;
}
.fp-clear:hover:not(:disabled) { text-decoration: underline; }
.fp-clear:disabled { color: #a39d96; cursor: not-allowed; }
</style>
