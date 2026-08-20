<script setup>
import { onMounted, onUnmounted } from 'vue';

const props = defineProps({
    title:       { type: String, default: 'Are you sure?' },
    message:     { type: String, default: '' },
    confirmText: { type: String, default: 'Delete' },
    cancelText:  { type: String, default: 'Cancel' },
    loadingText: { type: String, default: 'Deleting…' },
    danger:      { type: Boolean, default: true },
    loading:     { type: Boolean, default: false },
});

const emit = defineEmits(['confirm', 'cancel']);

function cancel()  { if (!props.loading) emit('cancel'); }
function confirm() { if (!props.loading) emit('confirm'); }

function onEsc(e) { if (e.key === 'Escape') cancel(); }
onMounted(()   => document.addEventListener('keydown', onEsc));
onUnmounted(() => document.removeEventListener('keydown', onEsc));
</script>

<template>
    <Teleport to="body">
        <div class="cfm-scrim" @click.self="cancel">
            <div class="cfm" role="alertdialog" aria-modal="true">
                <div class="cfm-ico" :class="{ 'cfm-ico-danger': danger }">
                    <i :class="danger ? 'bx bx-trash' : 'bx bx-help-circle'"></i>
                </div>
                <h2 class="cfm-title">{{ title }}</h2>
                <p v-if="message" class="cfm-msg">{{ message }}</p>
                <slot />
                <div class="cfm-actions">
                    <button class="mp-btn" :disabled="loading" @click="cancel">{{ cancelText }}</button>
                    <button
                        class="mp-btn"
                        :class="danger ? 'mp-btn-danger' : 'mp-btn-primary'"
                        :disabled="loading"
                        @click="confirm"
                    >{{ loading ? loadingText : confirmText }}</button>
                </div>
            </div>
        </div>
    </Teleport>
</template>

<style scoped>
@keyframes cfm-fade { from { opacity: 0; } to { opacity: 1; } }
@keyframes cfm-pop  { from { opacity: 0; transform: translateY(10px) scale(.97); } to { opacity: 1; transform: none; } }

.cfm-scrim {
    position: fixed; inset: 0; z-index: 1100;
    background: rgba(26,22,20,.45);
    display: flex; align-items: center; justify-content: center;
    padding: 16px; animation: cfm-fade .15s ease;
}
.cfm {
    background: #fff; border: 1px solid #e8e4db; border-radius: 14px;
    width: 100%; max-width: 380px;
    box-shadow: 0 20px 60px rgba(0,0,0,.18);
    padding: 26px 26px 22px; text-align: center;
    animation: cfm-pop .18s cubic-bezier(.34,1.3,.64,1);
}
.cfm-ico {
    width: 48px; height: 48px; margin: 0 auto 16px;
    display: flex; align-items: center; justify-content: center;
    border-radius: 50%; font-size: 22px;
    background: rgba(15,118,110,.12); color: #0f766e;
}
.cfm-ico-danger { background: #fdecec; color: #991b1b; }
.cfm-title { font-size: 16px; font-weight: 700; color: #1a1614; margin: 0 0 6px; }
.cfm-msg { font-size: 13px; color: #76706a; margin: 0; line-height: 1.5; }

.cfm-actions { display: flex; gap: 8px; justify-content: center; margin-top: 20px; }
.mp-btn {
    display: inline-flex; align-items: center; justify-content: center; gap: 6px;
    padding: 8px 16px; border-radius: 7px; min-width: 96px;
    border: 1px solid #e8e4db; background: #fff;
    font-size: 13px; font-weight: 500; color: #1a1614; cursor: pointer;
    transition: background .12s, border-color .12s;
}
.mp-btn:hover { background: #fbfaf6; border-color: #3d3833; }
.mp-btn:disabled { opacity: .6; cursor: default; }
.mp-btn-primary { background: #0f766e; border-color: #0f766e; color: #fff; }
.mp-btn-primary:hover { background: #0d9488; border-color: #0d9488; }
.mp-btn-danger { background: #991b1b; border-color: #991b1b; color: #fff; }
.mp-btn-danger:hover { background: #7f1616; border-color: #7f1616; }
</style>
