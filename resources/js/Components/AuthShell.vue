<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    title:    { type: String, default: '' },
    subtitle: { type: String, default: '' },
    /** Widen the card for forms with more fields (e.g. Register). */
    wide:     { type: Boolean, default: false },
});
</script>

<template>
    <div class="as-page">
        <div class="as-card" :class="{ 'as-card-wide': wide }">
            <Link :href="route('mylogin')" class="as-brand">
                <div class="as-mark">T+</div>
                <div class="as-brandtxt">
                    <div class="as-brandname">Tajheez<span class="as-plus">+</span></div>
                    <div class="as-brandsub">EMS Platform</div>
                </div>
            </Link>

            <div v-if="title" class="as-head">
                <h1 class="as-title">{{ title }}</h1>
                <p v-if="subtitle" class="as-subtitle">{{ subtitle }}</p>
            </div>

            <slot />
        </div>

        <div v-if="$slots.footer" class="as-foot">
            <slot name="footer" />
        </div>
    </div>
</template>

<style scoped>
.as-page {
    min-height: 100vh;
    display: flex; flex-direction: column; align-items: center; justify-content: center;
    gap: 18px;
    padding: 32px 16px;
    background: #f6f5f1;
}

.as-card {
    width: 100%; max-width: 400px;
    background: #fff;
    border: 1px solid #e8e4db;
    border-radius: 14px;
    box-shadow: 0 1px 3px rgba(26,22,20,.04), 0 12px 32px rgba(26,22,20,.06);
    padding: 28px 30px 30px;
}
.as-card-wide { max-width: 440px; }

.as-brand {
    display: flex; align-items: center; gap: 10px;
    text-decoration: none;
    margin-bottom: 22px;
}
.as-mark {
    width: 36px; height: 36px; flex-shrink: 0;
    background: #1a1614; color: #fff;
    border-radius: 8px;
    display: flex; align-items: center; justify-content: center;
    font-size: 14px; font-weight: 700;
}
.as-brandname { font-size: 14px; font-weight: 700; color: #1a1614; line-height: 1.2; }
.as-plus { color: #0f766e; }
.as-brandsub { font-size: 10px; color: #76706a; letter-spacing: .04em; }

.as-head { margin-bottom: 22px; }
.as-title { font-size: 20px; font-weight: 700; color: #1a1614; margin: 0 0 4px; }
.as-subtitle { font-size: 13px; color: #76706a; margin: 0; line-height: 1.5; }

.as-foot { font-size: 13px; color: #76706a; text-align: center; }
.as-foot :deep(a) { color: #0f766e; font-weight: 600; text-decoration: none; }
.as-foot :deep(a:hover) { text-decoration: underline; }

/* ── Form element polish (applies to bootstrap .form-control/.form-label/.btn inside the slot) ── */
.as-card :deep(.form-label) {
    font-size: 12.5px; font-weight: 600; color: #1a1614; margin-bottom: 5px;
}
.as-card :deep(.form-control) {
    border: 1px solid #e8e4db; border-radius: 8px;
    padding: 9px 12px; font-size: 13.5px; color: #1a1614;
    background: #fff;
}
.as-card :deep(.form-control:focus) {
    border-color: #0f766e; box-shadow: 0 0 0 3px rgba(15,118,110,.12);
}
.as-card :deep(.form-control.is-invalid) { border-color: #dc2626; }
.as-card :deep(.btn-primary) {
    border-radius: 8px; padding: 10px 14px; font-size: 13.5px; font-weight: 600;
    border: none;
}
.as-card :deep(.alert) { border-radius: 8px; font-size: 13px; padding: 10px 12px; }
</style>
