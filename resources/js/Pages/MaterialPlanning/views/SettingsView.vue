<script setup>
import { ref, computed } from 'vue';

const props = defineProps({
    event: Object,
    approvalsEnabled: { type: Boolean, default: true },
    approvalsEnabledSaving: { type: Boolean, default: false },
});
const emit = defineEmits(['update:approvalsEnabled']);

// ── Sub-nav ──────────────────────────────────────────────────────────────────
const sections = [
    { id: 'general',      label: 'General',            icon: 'bx bx-cog' },
    { id: 'modules',      label: 'Modules',             icon: 'bx bx-grid-alt' },
    { id: 'account',      label: 'Account & profile',   icon: 'bx bx-user' },
    { id: 'users',        label: 'Users & roles',       icon: 'bx bx-group' },
    { id: 'roles-perms',  label: 'Roles & Permissions', icon: 'bx bx-lock-alt' },
    { id: 'notifications',label: 'Notifications',       icon: 'bx bx-bell' },
    { id: 'email',        label: 'Email templates',     icon: 'bx bx-envelope' },
    { id: 'branding',     label: 'Branding',            icon: 'bx bx-palette' },
    { id: 'data-export',  label: 'Data & export',       icon: 'bx bx-download' },
];
const activeSection = ref('general');

// ── General settings ─────────────────────────────────────────────────────────
const dateFormatOptions = [
    { id: 'DD/MM/YYYY', label: 'DD/MM/YYYY' },
    { id: 'MM/DD/YYYY', label: 'MM/DD/YYYY' },
    { id: 'YYYY-MM-DD', label: 'YYYY-MM-DD' },
];
const dateFormat = ref('DD/MM/YYYY');

function formatToday(pattern) {
    const d = new Date();
    const dd = String(d.getDate()).padStart(2, '0');
    const mm = String(d.getMonth() + 1).padStart(2, '0');
    const yyyy = d.getFullYear();
    if (pattern === 'MM/DD/YYYY') return `${mm}/${dd}/${yyyy}`;
    if (pattern === 'YYYY-MM-DD') return `${yyyy}-${mm}-${dd}`;
    return `${dd}/${mm}/${yyyy}`;
}
const dateFormatPreview = computed(() => formatToday(dateFormat.value));

// ── Save ──────────────────────────────────────────────────────────────────────
const saving = ref(false);
const saved = ref(false);
function savePreferences() {
    saving.value = true;
    saved.value = false;
    setTimeout(() => {
        saving.value = false;
        saved.value = true;
        setTimeout(() => saved.value = false, 2000);
    }, 500);
}
</script>

<template>
    <div class="mp-page">
        <div class="mp-page-head">
            <div>
                <h1 class="mp-page-title">Settings</h1>
                <p class="mp-page-sub">Configure {{ event?.name || 'this event' }} and your Tajheez workspace.</p>
            </div>
        </div>

        <div class="st-layout">
            <!-- Sub-nav -->
            <nav class="st-nav">
                <button
                    v-for="s in sections" :key="s.id"
                    class="st-nav-item"
                    :class="{ 'st-nav-active': activeSection === s.id }"
                    @click="activeSection = s.id"
                >
                    <i :class="s.icon"></i>
                    <span>{{ s.label }}</span>
                </button>
            </nav>

            <!-- Content -->
            <div class="st-panel">
                <template v-if="activeSection === 'general'">
                    <div class="st-panel-head">
                        <h3 class="st-panel-title">General</h3>
                        <p class="st-panel-sub">Configure application-wide behaviour</p>
                    </div>

                    <div class="st-row">
                        <div class="st-row-ico"><i class="bx bx-calendar"></i></div>
                        <div class="st-row-meta">
                            <div class="st-row-label">Date Format</div>
                            <div class="st-row-desc">How dates display across the app and in emails for this event</div>
                        </div>
                        <div class="st-row-ctrl">
                            <select v-model="dateFormat" class="st-select">
                                <option v-for="o in dateFormatOptions" :key="o.id" :value="o.id">
                                    {{ o.label }} ({{ formatToday(o.id) }})
                                </option>
                            </select>
                        </div>
                    </div>

                    <div class="st-row">
                        <div class="st-row-ico"><i class="bx bx-check-shield"></i></div>
                        <div class="st-row-meta">
                            <div class="st-row-label">Enable Approvals</div>
                            <div class="st-row-desc">Require approval routing on new material requests before they can be submitted</div>
                        </div>
                        <div class="st-row-ctrl">
                            <button
                                class="st-toggle"
                                :class="{ 'st-toggle-on': approvalsEnabled }"
                                role="switch"
                                :aria-checked="approvalsEnabled"
                                :disabled="approvalsEnabledSaving"
                                @click="emit('update:approvalsEnabled', !approvalsEnabled)"
                            ><span class="st-toggle-knob"/></button>
                        </div>
                    </div>

                    <div class="st-panel-foot">
                        <button class="mp-btn mp-btn-primary" :disabled="saving" @click="savePreferences">
                            {{ saving ? 'Saving…' : saved ? 'Saved ✓' : 'Save Preferences' }}
                        </button>
                    </div>
                </template>

                <template v-else>
                    <div class="st-panel-head">
                        <h3 class="st-panel-title">{{ sections.find(s => s.id === activeSection)?.label }}</h3>
                    </div>
                    <div class="st-empty">
                        <i class="bx bx-wrench"></i>
                        <p>This section isn't set up yet.</p>
                    </div>
                </template>
            </div>
        </div>
    </div>
</template>

<style scoped>
.mp-page { max-width: 100%; }
.mp-page-head { margin-bottom: 20px; }
.mp-page-title { font-size: 22px; font-weight: 700; color: #1a1614; margin: 0; }
.mp-page-sub { font-size: 13px; color: #76706a; margin: 4px 0 0; }

.mp-btn {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 8px 16px; border-radius: 7px;
    border: 1px solid #e8e4db; background: #fff;
    font-size: 13px; color: #1a1614; cursor: pointer;
    transition: background .15s;
}
.mp-btn:hover { background: #f6f5f1; }
.mp-btn-primary { background: #0f766e; border-color: #0f766e; color: #fff; }
.mp-btn-primary:hover { background: #0d9488; }
.mp-btn-primary:disabled { opacity: .7; cursor: default; }

.st-layout { display: grid; grid-template-columns: 240px 1fr; gap: 20px; align-items: start; }

.st-nav {
    background: #fff; border: 1px solid #e8e4db; border-radius: 10px;
    padding: 6px; display: flex; flex-direction: column; gap: 1px;
}
.st-nav-item {
    display: flex; align-items: center; gap: 10px;
    padding: 9px 12px; border-radius: 7px;
    border: none; background: none; text-align: left;
    font-size: 13px; color: #3d3833; cursor: pointer;
    transition: background .12s;
}
.st-nav-item i { font-size: 16px; opacity: .85; flex-shrink: 0; }
.st-nav-item:hover { background: #fbfaf6; }
.st-nav-active { background: rgba(15,118,110,.12); color: #0f766e; font-weight: 600; }
.st-nav-active i { opacity: 1; }

.st-panel { background: #fff; border: 1px solid #e8e4db; border-radius: 10px; overflow: hidden; }
.st-panel-head { padding: 20px 24px 16px; border-bottom: 1px solid #f3f0ea; }
.st-panel-title { font-size: 17px; font-weight: 700; color: #1a1614; margin: 0; }
.st-panel-sub { font-size: 12.5px; color: #76706a; margin: 3px 0 0; }

.st-row {
    display: flex; align-items: flex-start; gap: 14px;
    padding: 18px 24px; border-bottom: 1px solid #f3f0ea;
}
.st-row-ico {
    width: 36px; height: 36px; flex-shrink: 0;
    display: flex; align-items: center; justify-content: center;
    background: #fbfaf6; border: 1px solid #e8e4db; border-radius: 8px;
    color: #76706a; font-size: 16px;
}
.st-row-meta { flex: 1; min-width: 0; }
.st-row-label { font-size: 13.5px; font-weight: 600; color: #1a1614; }
.st-row-desc { font-size: 12px; color: #76706a; margin-top: 2px; line-height: 1.4; }
.st-row-ctrl { flex-shrink: 0; padding-top: 2px; }

.st-select {
    border: 1px solid #e8e4db; border-radius: 7px;
    padding: 7px 10px; font-size: 13px; color: #1a1614;
    background: #fff; min-width: 200px; cursor: pointer;
}
.st-select:focus { outline: none; border-color: #0f766e; box-shadow: 0 0 0 3px rgba(15,118,110,.12); }

.st-toggle {
    width: 40px; height: 22px; border-radius: 20px;
    border: none; background: #d8d3c9; padding: 2px;
    display: flex; align-items: center; cursor: pointer;
    transition: background .15s;
}
.st-toggle:disabled { opacity: .6; cursor: default; }
.st-toggle-on { background: #0f766e; justify-content: flex-end; }
.st-toggle-knob { width: 18px; height: 18px; border-radius: 50%; background: #fff; box-shadow: 0 1px 2px rgba(0,0,0,.2); }

.st-panel-foot { display: flex; justify-content: flex-end; padding: 16px 24px; }

.st-empty {
    display: flex; flex-direction: column; align-items: center; gap: 8px;
    padding: 48px 24px; color: #a39d96; text-align: center;
}
.st-empty i { font-size: 26px; }
.st-empty p { font-size: 13px; margin: 0; }

@media (max-width: 860px) {
    .st-layout { grid-template-columns: 1fr; }
    .st-nav { flex-direction: row; flex-wrap: wrap; }
}
</style>
