<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';

const props = defineProps({
    count:           { type: Number, required: true },
    itemSubGroups:   { type: Array, default: () => [] }, // unique subgroups across the selected items
    serviceOptions:  { type: Array, default: () => [] },
    suppliers:       { type: Array, default: () => [] },
    saving:          { type: Boolean, default: false },
    error:           { type: String, default: null },
});

const emit = defineEmits(['close', 'assign']);

const activeOptions = computed(() => props.serviceOptions.filter(o => o.classificationName !== 'Suspended'));

// Narrow to bundles whose Item subgroup matches one of the selected items' —
// but only when that leaves something to pick (mixed/no subgroups fall back
// to the full list rather than dead-ending the picker).
const options = computed(() => {
    if (!props.itemSubGroups.length) return activeOptions.value;
    const targets = new Set(props.itemSubGroups.map(s => s.trim().toLowerCase()));
    const matches = activeOptions.value.filter(o => targets.has((o.itemSubgroupLabel || '').trim().toLowerCase()));
    return matches.length ? matches : activeOptions.value;
});
const isFiltered = computed(() => props.itemSubGroups.length && options.value.length < activeOptions.value.length);
const selectedId = ref(null);

function supplierOf(code) { return props.suppliers.find(s => s.code === code); }
function fmtMoney(n) { return '$' + Number(n || 0).toLocaleString('en-US'); }

function confirm() {
    if (!selectedId.value) return;
    emit('assign', selectedId.value);
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
                        <div class="skum-hd-tag"><span class="mono">{{ count }} item{{ count === 1 ? '' : 's' }}</span><span>·</span><span>Assign service</span></div>
                        <h2 class="skum-title">Assign a service bundle to {{ count }} item{{ count === 1 ? '' : 's' }}</h2>
                        <p class="skum-sub">
                            Pick which bundle fulfills the selected item{{ count === 1 ? '' : 's' }} — every service in the bundle applies to each, replacing whatever they currently carry.
                            <template v-if="isFiltered"> Showing bundles in the <b>{{ itemSubGroups.join(', ') }}</b> subgroup{{ itemSubGroups.length === 1 ? '' : 's' }}.</template>
                        </p>
                    </div>
                    <button class="skum-x" @click="close" aria-label="Close">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18"/></svg>
                    </button>
                </header>

                <div class="skum-body">
                    <div v-if="!options.length" class="asm-empty">
                        <p>No service option bundles exist yet — create one from the Service Options page first.</p>
                    </div>

                    <div v-else class="asm-list">
                        <label
                            v-for="o in options" :key="o.dbId"
                            class="asm-opt"
                            :class="{ 'asm-opt-on': selectedId === o.dbId }"
                        >
                            <input type="radio" name="basm-option" :value="o.dbId" v-model="selectedId"/>
                            <div class="asm-opt-body">
                                <div class="asm-opt-head">
                                    <span class="asm-opt-name">{{ o.name }}</span>
                                    <span v-if="o.isDefault" class="asm-opt-default">Default</span>
                                </div>
                                <div class="asm-opt-meta">
                                    <span class="mono">{{ fmtMoney(o.cost) }} total</span>
                                    <span>·</span>
                                    <span>{{ o.lead }}d lead</span>
                                </div>
                                <div v-if="o.services?.length" class="asm-opt-svcs">
                                    <span v-for="s in o.services" :key="s.id" class="asm-svc-chip">{{ s.name }} · {{ supplierOf(s.supplier)?.name ?? s.supplier }}</span>
                                </div>
                            </div>
                        </label>
                    </div>
                </div>

                <footer class="skum-ft">
                    <div class="skum-ft-l">
                        <span v-if="error" class="asm-err">{{ error }}</span>
                    </div>
                    <div class="skum-ft-r">
                        <button class="mp-btn" @click="close">Cancel</button>
                        <button
                            v-if="options.length"
                            class="mp-btn mp-btn-primary"
                            :disabled="!selectedId || saving"
                            @click="confirm"
                        >{{ saving ? 'Assigning…' : `Assign to ${count} item${count === 1 ? '' : 's'}` }}</button>
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
.mp-btn-primary { background: #1a1614; border-color: #1a1614; color: #fff; }
.mp-btn-primary:hover { background: #0a0806; border-color: #0a0806; }

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
.skum-body { padding: 20px 24px; overflow-y: auto; max-height: 56vh; }

.asm-empty { text-align: center; padding: 24px 8px; color: #76706a; font-size: 13px; }
.asm-empty p { margin: 0; }

.asm-list { display: flex; flex-direction: column; gap: 8px; }
.asm-opt {
    display: flex; align-items: flex-start; gap: 10px;
    border: 1px solid #e8e4db; border-radius: 8px;
    padding: 10px 12px; cursor: pointer; transition: background .12s, border-color .12s;
}
.asm-opt:hover { border-color: #a39d96; }
.asm-opt-on { border-color: #0f766e; background: rgba(15,118,110,.06); }
.asm-opt input[type="radio"] { margin-top: 3px; accent-color: #0f766e; flex-shrink: 0; }
.asm-opt-body { flex: 1; min-width: 0; }
.asm-opt-head { display: flex; align-items: center; gap: 8px; }
.asm-opt-name { font-size: 13.5px; font-weight: 600; color: #1a1614; }
.asm-opt-default {
    font-size: 10.5px; font-weight: 700; color: #0f766e;
    background: rgba(15,118,110,.12); padding: 1px 7px; border-radius: 20px;
}
.asm-opt-meta { display: flex; gap: 6px; flex-wrap: wrap; font-size: 12px; color: #76706a; margin-top: 3px; }
.asm-opt-svcs { display: flex; gap: 5px; flex-wrap: wrap; margin-top: 6px; }
.asm-svc-chip { font-size: 11px; color: #3d3833; background: #efece4; padding: 2px 7px; border-radius: 20px; }

.asm-err { font-size: 12.5px; color: #991b1b; }

.skum-ft {
    display: flex; align-items: center; justify-content: space-between;
    gap: 12px; padding: 16px 24px; border-top: 1px solid #e8e4db;
    background: #fbfaf6; border-radius: 0 0 13px 13px;
}
.skum-ft-l { display: flex; align-items: center; gap: 10px; min-width: 0; }
.skum-ft-r { display: flex; gap: 8px; flex-shrink: 0; }
</style>
