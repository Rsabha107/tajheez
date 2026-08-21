<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import axios from 'axios';

const props = defineProps({
    event: Object,
    approvalsEnabled: { type: Boolean, default: true },
    approvalsEnabledSaving: { type: Boolean, default: false },
    showItemValues: { type: Boolean, default: false },
    showItemValuesSaving: { type: Boolean, default: false },
});
const emit = defineEmits(['update:approvalsEnabled', 'update:showItemValues', 'go-to']);

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

// ── Users & roles ──────────────────────────────────────────────────────────────
const page = usePage();
const currentUserId = computed(() => page.props.auth?.user?.id ?? null);

const users = ref([]);
const usersLoading = ref(false);
const usersLoaded = ref(false);
const userSearch = ref('');

const filteredUsers = computed(() => {
    const q = userSearch.value.trim().toLowerCase();
    if (!q) return users.value;
    return users.value.filter(u => u.name?.toLowerCase().includes(q) || u.email?.toLowerCase().includes(q));
});

async function fetchUsers() {
    usersLoading.value = true;
    try {
        const { data } = await axios.get(route('users.data'), { params: { limit: 100, offset: 0 } });
        users.value = data.rows ?? data ?? [];
        usersLoaded.value = true;
    } catch (_) {
        // leave the list empty — the panel's empty-state covers this
    } finally {
        usersLoading.value = false;
    }
}
watch(activeSection, id => { if (id === 'users' && !usersLoaded.value) fetchUsers(); }, { immediate: true });

function statusStyle(color) {
    return {
        success:   { bg: '#dcfce7', fg: '#16a34a', dot: '#16a34a' },
        danger:    { bg: '#fee2e2', fg: '#dc2626', dot: '#dc2626' },
        warning:   { bg: '#fef3c7', fg: '#b45309', dot: '#b45309' },
        info:      { bg: '#dbeafe', fg: '#1d4ed8', dot: '#1d4ed8' },
        secondary: { bg: '#f1f5f9', fg: '#64748b', dot: '#94a3b8' },
    }[color] || { bg: '#f1f5f9', fg: '#64748b', dot: '#94a3b8' };
}

const faPalette = [
    { bg: '#ccfbf1', fg: '#0f766e' },
    { bg: '#e0f2fe', fg: '#0369a1' },
    { bg: '#d1fae5', fg: '#047857' },
    { bg: '#e2e8f0', fg: '#475569' },
];
function faStyle(i) { return faPalette[i % faPalette.length]; }

const avatarColors = ['#7c2d12','#0f766e','#b45309','#1d4ed8','#6b21a8','#155e75','#854d0e'];
function initialsOf(name) {
    if (!name) return '—';
    const parts = name.trim().split(/\s+/);
    return ((parts[0]?.[0] || '') + (parts[1]?.[0] || '')).toUpperCase();
}
function avatarColor(name) {
    const key = initialsOf(name);
    const h = (key.charCodeAt(0) + (key.charCodeAt(1) || 0)) % avatarColors.length;
    return avatarColors[h];
}

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

                    <div class="st-row">
                        <div class="st-row-ico"><i class="bx bx-dollar"></i></div>
                        <div class="st-row-meta">
                            <div class="st-row-label">Show Item Values</div>
                            <div class="st-row-desc">Show rates and totals on the requests list and each request's items detail</div>
                        </div>
                        <div class="st-row-ctrl">
                            <button
                                class="st-toggle"
                                :class="{ 'st-toggle-on': showItemValues }"
                                role="switch"
                                :aria-checked="showItemValues"
                                :disabled="showItemValuesSaving"
                                @click="emit('update:showItemValues', !showItemValues)"
                            ><span class="st-toggle-knob"/></button>
                        </div>
                    </div>

                    <div class="st-panel-foot">
                        <button class="mp-btn mp-btn-primary" :disabled="saving" @click="savePreferences">
                            {{ saving ? 'Saving…' : saved ? 'Saved ✓' : 'Save Preferences' }}
                        </button>
                    </div>
                </template>

                <template v-else-if="activeSection === 'users'">
                    <div class="st-panel-head st-panel-head-row">
                        <div>
                            <h3 class="st-panel-title">Users</h3>
                            <p class="st-panel-sub">People with access to this Tajheez workspace.</p>
                        </div>
                        <div class="st-users-search">
                            <i class="bx bx-search"></i>
                            <input v-model="userSearch" placeholder="Search members…"/>
                        </div>
                    </div>

                    <div v-if="usersLoading" class="st-empty">
                        <i class="bx bx-loader-alt bx-spin"></i>
                        <p>Loading users…</p>
                    </div>
                    <div v-else-if="!filteredUsers.length" class="st-empty">
                        <i class="bx bx-user-x"></i>
                        <p>No users match this search.</p>
                    </div>
                    <template v-else>
                        <div class="st-users-colhead">
                            <span></span>
                            <span>User</span>
                            <span>Status</span>
                            <span>Functional Areas</span>
                            <span>Role</span>
                            <span></span>
                        </div>
                        <ul class="st-users-list">
                            <li v-for="u in filteredUsers" :key="u.id" class="st-user-row">
                                <span class="st-user-avatar" :style="{ background: avatarColor(u.name) }">{{ initialsOf(u.name) }}</span>
                                <div class="st-user-meta">
                                    <div class="st-user-name">
                                        {{ u.name }}
                                        <span v-if="u.id === currentUserId" class="st-user-you">You</span>
                                    </div>
                                    <div class="st-user-email">{{ u.email }}</div>
                                </div>
                                <div class="st-user-status">
                                    <span v-if="u.status_name" class="mp-pill" :style="{ background: statusStyle(u.status_color).bg, color: statusStyle(u.status_color).fg }">
                                        <span class="mp-pill-dot" :style="{ background: statusStyle(u.status_color).dot }"/>
                                        {{ u.status_name }}
                                    </span>
                                    <span v-else class="st-user-role-empty">—</span>
                                </div>
                                <div class="st-user-fas">
                                    <span v-if="!(u.functionalAreas ?? []).length" class="st-user-role-empty">No FA</span>
                                    <template v-else>
                                        <span
                                            v-for="(fa, i) in u.functionalAreas.slice(0, 2)" :key="fa.id ?? i"
                                            class="st-user-fa-pill"
                                            :style="{ background: faStyle(i).bg, color: faStyle(i).fg }"
                                        >{{ fa.title }}</span>
                                        <span v-if="u.functionalAreas.length > 2" class="st-user-role-empty">+{{ u.functionalAreas.length - 2 }}</span>
                                    </template>
                                </div>
                                <div class="st-user-roles">
                                    <span v-if="!(u.roles ?? []).length" class="st-user-role-empty">No role</span>
                                    <span v-else class="st-user-role-pill">{{ u.roles[0].name }}<template v-if="u.roles.length > 1"> +{{ u.roles.length - 1 }}</template></span>
                                </div>
                                <button class="st-user-menu" title="Manage in Users" @click="emit('go-to', 'users')"><i class="bx bx-dots-vertical-rounded"></i></button>
                            </li>
                        </ul>
                    </template>

                    <div class="st-panel-foot">
                        <button class="mp-btn mp-btn-primary" @click="emit('go-to', 'users')"><i class="bx bx-plus"></i> Add user</button>
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

.st-panel-head-row { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; }
.st-users-search {
    display: flex; align-items: center; gap: 7px;
    border: 1px solid #e8e4db; border-radius: 7px;
    padding: 7px 11px; color: #76706a; min-width: 220px;
}
.st-users-search i { font-size: 15px; }
.st-users-search input { border: none; outline: none; background: transparent; flex: 1; font-size: 13px; color: #1a1614; }

.st-users-colhead {
    display: grid; grid-template-columns: 34px 1fr 110px 190px 130px 28px; gap: 12px;
    padding: 0 24px 8px; margin-top: 14px;
    font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: #a39d96;
}
.st-users-list { list-style: none; margin: 0; padding: 0; }
.st-user-row {
    display: grid; grid-template-columns: 34px 1fr 110px 190px 130px 28px; align-items: center; gap: 12px;
    padding: 12px 24px; border-bottom: 1px solid #f3f0ea;
}
.st-user-row:last-child { border-bottom: none; }
.st-user-avatar {
    width: 34px; height: 34px; border-radius: 50%; flex-shrink: 0;
    display: inline-grid; place-items: center;
    color: #fff; font-size: 12px; font-weight: 700;
}
.st-user-meta { min-width: 0; }
.st-user-name { display: flex; align-items: center; gap: 8px; font-size: 13.5px; font-weight: 600; color: #1a1614; }
.st-user-you {
    font-size: 10.5px; font-weight: 600; color: #76706a;
    background: #f3f0e8; padding: 1px 7px; border-radius: 20px;
}
.st-user-email { font-size: 12px; color: #76706a; margin-top: 1px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.st-user-status, .st-user-fas, .st-user-roles { display: flex; align-items: center; gap: 4px; flex-wrap: wrap; min-width: 0; }
.st-user-role-pill {
    font-size: 11.5px; font-weight: 600; color: #0f766e;
    background: rgba(15,118,110,.12); padding: 3px 10px; border-radius: 20px;
    text-transform: capitalize; white-space: nowrap;
}
.st-user-fa-pill {
    font-size: 11px; font-weight: 600; padding: 2px 9px; border-radius: 20px; white-space: nowrap;
}
.st-user-role-empty { font-size: 12px; color: #a39d96; }
.mp-pill { display: inline-flex; align-items: center; gap: 5px; padding: 3px 9px; border-radius: 20px; font-size: 11.5px; font-weight: 600; }
.mp-pill-dot { width: 6px; height: 6px; border-radius: 50%; flex-shrink: 0; }
.st-user-menu {
    width: 28px; height: 28px; flex-shrink: 0;
    display: inline-flex; align-items: center; justify-content: center;
    border: 1px solid #e8e4db; border-radius: 6px; background: #fff;
    color: #76706a; cursor: pointer; transition: background .12s;
}
.st-user-menu:hover { background: #fbfaf6; }

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
