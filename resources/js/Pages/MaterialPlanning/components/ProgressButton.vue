<script setup>
import { computed } from 'vue';

const props = defineProps({
    loading:     { type: Boolean, default: false },
    disabled:    { type: Boolean, default: false },
    text:        { type: String, default: null },
    loadingText: { type: String, default: null },
    // 'primary' | 'danger' | 'default' | 'link' style the button themselves
    // (MP design system). 'bootstrap' + icon-only mode add no visuals of
    // their own at all — every color/size comes from whatever classes the
    // caller passes through (e.g. "btn btn-primary", "mp-icon-edit",
    // "btn-close"), so this component drops cleanly into a Bootstrap-themed
    // view without fighting its existing styling.
    variant:     { type: String, default: 'primary' },
    type:        { type: String, default: 'button' },
    iconOnly:    { type: Boolean, default: false },
    // Optional theme-color override for the 'primary'/'danger'/'link'
    // variants, so one component can match each view's own accent color.
    color:       { type: String, default: null },
    hoverColor:  { type: String, default: null },
});

const emit = defineEmits(['click']);

function onClick(e) {
    if (props.loading || props.disabled) return;
    emit('click', e);
}

const styleVars = computed(() => ({
    ...(props.color ? { '--pgb-bg': props.color } : {}),
    ...(props.hoverColor ? { '--pgb-bg-hover': props.hoverColor } : {}),
}));
</script>

<template>
    <button
        :type="type"
        class="pgb"
        :class="[iconOnly ? 'pgb-icon' : `pgb-${variant}`, { 'pgb-loading': loading }]"
        :style="styleVars"
        :disabled="disabled || loading"
        @click="onClick"
    >
        <template v-if="iconOnly">
            <span v-if="loading" class="pgb-spinner" aria-hidden="true"></span>
            <slot v-else />
        </template>
        <template v-else>
            <span v-if="loading" class="pgb-spinner" aria-hidden="true"></span>
            <span class="pgb-label">{{ loading ? (loadingText ?? text) : text }}</span>
            <span v-if="loading" class="pgb-bar" aria-hidden="true"></span>
        </template>
    </button>
</template>

<style scoped>
/* Base: layout/behavior only — no box-model or color here, so it never
   fights a foreign design system (Bootstrap, table icon tints, …) when
   this component is used purely for its loading/click-guard mechanics. */
.pgb {
    position: relative; overflow: hidden;
    display: inline-flex; align-items: center; justify-content: center; gap: 7px;
    cursor: pointer;
}
.pgb:disabled { opacity: .75; }
.pgb-loading { cursor: progress; }
.pgb-bootstrap, .pgb-icon { min-width: 0; }

/* Labeled MP-theme variants — each opts into the full look itself. */
.pgb-primary, .pgb-danger, .pgb-default, .pgb-link {
    padding: 8px 16px; border-radius: 7px; min-width: 96px;
    border: 1px solid #e8e4db; background: #fff;
    font-size: 13px; font-weight: 500; color: #1a1614;
    transition: background .12s, border-color .12s, opacity .12s, color .12s;
}
.pgb-primary:hover:not(:disabled), .pgb-default:hover:not(:disabled) { background: #fbfaf6; border-color: #3d3833; }

.pgb-primary { background: var(--pgb-bg, #1a1614); border-color: var(--pgb-bg, #1a1614); color: #fff; }
.pgb-primary:hover:not(:disabled) { background: var(--pgb-bg-hover, #0a0806); border-color: var(--pgb-bg-hover, #0a0806); }
.pgb-danger { background: var(--pgb-bg, #991b1b); border-color: var(--pgb-bg, #991b1b); color: #fff; }
.pgb-danger:hover:not(:disabled) { background: var(--pgb-bg-hover, #7f1616); border-color: var(--pgb-bg-hover, #7f1616); }

.pgb-link {
    min-width: 0; padding: 2px 0; border: none; background: transparent;
    color: var(--pgb-bg, #76706a); font-size: 12px; font-weight: 500;
}
.pgb-link:hover:not(:disabled) { background: transparent; color: var(--pgb-bg-hover, #1a1614); text-decoration: underline; }

.pgb-spinner {
    width: 13px; height: 13px; flex-shrink: 0;
    border: 2px solid rgba(26,22,20,.15); border-top-color: currentColor;
    border-radius: 50%;
    animation: pgb-spin .7s linear infinite;
}
.pgb-primary .pgb-spinner, .pgb-danger .pgb-spinner, .pgb-bootstrap .pgb-spinner {
    border-color: rgba(255,255,255,.35); border-top-color: currentColor;
}

.pgb-bar {
    position: absolute; left: -40%; bottom: 0; height: 2px; width: 40%;
    background: currentColor; opacity: .55; border-radius: 2px;
    animation: pgb-slide 1.1s ease-in-out infinite;
}

@keyframes pgb-spin { to { transform: rotate(360deg); } }
@keyframes pgb-slide {
    0%   { left: -40%; }
    100% { left: 100%; }
}
</style>
