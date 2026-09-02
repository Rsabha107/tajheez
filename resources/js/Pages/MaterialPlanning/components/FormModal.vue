<script setup>
import { onMounted, onUnmounted } from 'vue';

const props = defineProps({
    show:       { type: Boolean, default: true },
    title:      { type: String, required: true },
    subtitle:   { type: String, default: '' },
    maxWidth:   { type: String, default: '680px' },
    cancelText: { type: String, default: 'Cancel' },
});

const emit = defineEmits(['close']);

function close() { emit('close'); }

function onEsc(e) { if (e.key === 'Escape' && props.show) close(); }
onMounted(()   => document.addEventListener('keydown', onEsc));
onUnmounted(() => document.removeEventListener('keydown', onEsc));
</script>

<template>
    <Teleport to="body">
        <div v-if="show" class="fm-scrim" @click.self="close">
            <div class="fm-box" :style="{ maxWidth }" role="dialog" aria-modal="true">
                <header class="fm-hd">
                    <div class="fm-hd-l">
                        <div v-if="$slots.eyebrow" class="fm-hd-tag"><slot name="eyebrow" /></div>
                        <h2 class="fm-title">{{ title }}</h2>
                        <p v-if="subtitle" class="fm-sub">{{ subtitle }}</p>
                    </div>
                    <button class="fm-x" @click="close" aria-label="Close">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18"/></svg>
                    </button>
                </header>

                <div class="fm-body">
                    <slot />
                </div>

                <footer class="fm-ft">
                    <div class="fm-ft-l">
                        <slot name="footer-left" />
                    </div>
                    <div class="fm-ft-r">
                        <button class="fm-cancel" @click="close">{{ cancelText }}</button>
                        <slot name="footer-actions" />
                    </div>
                </footer>
            </div>
        </div>
    </Teleport>
</template>

<style scoped>
@keyframes fm-fade { from { opacity: 0; } to { opacity: 1; } }
@keyframes fm-pop  { from { opacity: 0; transform: translateY(14px) scale(.97); } to { opacity: 1; transform: none; } }

.fm-scrim {
    position: fixed; inset: 0; z-index: 1000;
    background: rgba(26,22,20,.45);
    display: flex; align-items: flex-start; justify-content: center;
    padding: 40px 16px; overflow-y: auto;
    animation: fm-fade .18s ease;
}
.fm-box {
    background: #fff; border: 1px solid #e8e4db; border-radius: 14px;
    width: 100%;
    box-shadow: 0 20px 60px rgba(0,0,0,.18);
    animation: fm-pop .22s cubic-bezier(.34,1.3,.64,1);
    display: flex; flex-direction: column;
}
.fm-hd {
    display: flex; align-items: flex-start; justify-content: space-between;
    padding: 22px 24px 18px; border-bottom: 1px solid #e8e4db;
    background: #fbfaf6; border-radius: 13px 13px 0 0;
}
.fm-hd-tag {
    display: inline-flex; align-items: center; gap: 6px;
    font-size: 11px; color: #76706a; margin-bottom: 6px;
    background: #efece4; padding: 2px 8px; border-radius: 20px;
}
.fm-title { font-size: 17px; font-weight: 700; color: #1a1614; margin: 0 0 4px; }
.fm-sub   { font-size: 12.5px; color: #76706a; margin: 0; line-height: 1.5; }
.fm-x {
    width: 30px; height: 30px; border-radius: 7px;
    border: 1px solid #e8e4db; background: #fff;
    display: inline-flex; align-items: center; justify-content: center;
    cursor: pointer; color: #76706a; flex-shrink: 0; margin-left: 12px;
    transition: background .12s;
}
.fm-x:hover { background: #f6f5f1; }
.fm-body { padding: 0 24px; overflow-y: auto; max-height: 62vh; }
.fm-ft {
    display: flex; align-items: center; justify-content: space-between;
    gap: 12px; padding: 16px 24px;
    border-top: 1px solid #e8e4db;
    background: #fbfaf6; border-radius: 0 0 13px 13px;
}
.fm-ft-l { display: flex; align-items: center; gap: 10px; min-width: 0; }
.fm-ft-r { display: flex; gap: 8px; flex-shrink: 0; }
.fm-cancel {
    display: inline-flex; align-items: center; justify-content: center; gap: 6px;
    padding: 8px 16px; border-radius: 7px; min-width: 96px;
    border: 1px solid #e8e4db; background: #fff;
    font-size: 13px; font-weight: 500; color: #1a1614; cursor: pointer;
    transition: background .12s, border-color .12s;
}
.fm-cancel:hover { background: #fbfaf6; border-color: #3d3833; }
</style>
