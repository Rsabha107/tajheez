<script setup>
defineProps({
    refreshing: { type: Boolean, default: false },
    title:      { type: String, default: 'Refresh' },
});

const emit = defineEmits(['click']);
</script>

<template>
    <button class="mp-refresh-btn" :title="title" :disabled="refreshing" @click="emit('click')">
        <svg :class="{ spin: refreshing }" viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
            <path d="M23 4v6h-6"/><path d="M1 20v-6h6"/>
            <path d="M3.51 9a9 9 0 0114.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0020.49 15"/>
        </svg>
    </button>
</template>

<style scoped>
.mp-refresh-btn {
    display: inline-flex; align-items: center; justify-content: center;
    width: 34px; height: 34px; padding: 0;
    border-radius: 7px; border: 1px solid #e8e4db; background: #fff;
    color: #76706a; cursor: pointer; transition: background .15s, color .15s;
}
.mp-refresh-btn:hover { background: #fbfaf6; color: #1a1614; }
.mp-refresh-btn:disabled { cursor: default; }

@keyframes mp-refresh-spin { to { transform: rotate(360deg); } }
.spin { animation: mp-refresh-spin .8s linear infinite; }
</style>
