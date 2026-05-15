<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import { Head } from '@inertiajs/vue3';
import axios from 'axios';

import DashboardView   from './views/DashboardView.vue';
import RequestsView    from './views/RequestsView.vue';
import ApprovalsView   from './views/ApprovalsView.vue';
import NewRequestView  from './views/NewRequestView.vue';
import CatalogView     from './views/CatalogView.vue';
import ReportsView     from './views/ReportsView.vue';
import DetailView      from './views/DetailView.vue';
import EventsView            from './views/EventsView.vue';
import VenuesView            from './views/VenuesView.vue';
import FunctionalAreasView   from './views/FunctionalAreasView.vue';
import PermissionsView       from './views/PermissionsView.vue';
import RolesView             from './views/RolesView.vue';
import RolesPermissionsView  from './views/RolesPermissionsView.vue';
import UsersView             from './views/UsersView.vue';

const props = defineProps({
    event:    Object,
    venues:   Array,
    domains:  Array,
    statuses: Object,
    people:   Array,
    requests: Array,
    catalog:  Array,
});

// ── Navigation ─────────────────────────────────────────────────────────────
const activePage       = ref('dash');
const detailId         = ref(null);
const sidebarCollapsed = ref(false);

const nav = [
    { id: 'dash',      label: 'Dashboard',    icon: 'bx bx-grid-alt',       badge: null },
    { id: 'requests',  label: 'Requests',     icon: 'bx bx-list-ul',         badge: '7' },
    { id: 'new',       label: 'New Request',  icon: 'bx bx-plus-circle',     badge: null },
    { id: 'catalog',   label: 'Catalog',      icon: 'bx bx-book-open',       badge: null },
    { id: 'approvals', label: 'Approvals',    icon: 'bx bx-check-shield',    badge: '3' },
    { id: 'reports',   label: 'Reports',      icon: 'bx bx-bar-chart-alt-2', badge: null },
];

function openRequest(id) {
    detailId.value = id;
    activePage.value = 'detail';
}
const prefilledSku = ref(null);
function goTo(id, payload = null) {
    activePage.value = id;
    prefilledSku.value = (id === 'new' && payload?.sku) ? payload.sku : null;
}

const sectionLabels = {
    events: 'Events', venues: 'Venues', 'functional-areas': 'Functional Areas',
    permissions: 'Permissions', roles: 'Roles', 'roles-permissions': 'Roles & Permissions', users: 'Users',
};
const currentPageLabel = computed(() => nav.find(n => n.id === activePage.value)?.label || sectionLabels[activePage.value] || '');

// ── Event selector ──────────────────────────────────────────────────────────
const STORAGE_KEY = 'mp_active_event_id';
const availableEvents = ref([]);
const selectedEventId = ref(Number(localStorage.getItem(STORAGE_KEY)) || props.event?.id || null);

watch(selectedEventId, id => {
    if (id) localStorage.setItem(STORAGE_KEY, id);
    else localStorage.removeItem(STORAGE_KEY);
});

function eventCode(ev) {
    if (!ev) return '—';
    if (ev.code) return ev.code;
    return (ev.name ?? '').split(/\s+/).map(w => w[0]).join('').toUpperCase().slice(0, 6) || '—';
}

const activeEvent = computed(() => {
    if (!selectedEventId.value || !availableEvents.value.length) return props.event;
    const found = availableEvents.value.find(e => e.id === selectedEventId.value);
    if (!found) return props.event;
    if (found.id === props.event?.id) return props.event;
    return { id: found.id, code: eventCode(found), name: found.name, window: null, daysOut: null };
});

const evtDropdownOpen = ref(false);
function selectEvent(id) { selectedEventId.value = id; evtDropdownOpen.value = false; }

const vClickOutside = {
    mounted(el, b)   { el._co = e => { if (!el.contains(e.target)) b.value(e); }; document.addEventListener('click', el._co); },
    unmounted(el)    { document.removeEventListener('click', el._co); },
};

onMounted(async () => {
    try {
        const { data } = await axios.get(route('events.data'), { params: { limit: 100, offset: 0 } });
        availableEvents.value = data.rows ?? data ?? [];
        if (!selectedEventId.value && availableEvents.value.length) {
            selectedEventId.value = availableEvents.value[0].id;
        }
    } catch (_) {}
});
</script>

<template>
    <Head title="Material Planning" />

    <div class="mp-shell">

        <!-- ── MP Sidebar ──────────────────────────────────────── -->
        <aside class="mp-sidebar" :class="{ 'mp-sidebar-collapsed': sidebarCollapsed }">
            <div class="mp-brand">
                <div class="mp-mark">T+</div>
                <div class="mp-brandtxt">
                    <div class="mp-brandname">Tajheez<span class="mp-plus">+</span></div>
                    <div class="mp-brandsub">Material planning</div>
                </div>
                <button class="mp-toggle-btn" @click="sidebarCollapsed = !sidebarCollapsed" :title="sidebarCollapsed ? 'Expand sidebar' : 'Collapse sidebar'">
                    <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M15 18l-6-6 6-6"/>
                    </svg>
                </button>
            </div>

            <nav class="mp-nav">
                <button
                    v-for="n in nav"
                    :key="n.id"
                    class="mp-nav-item"
                    :class="{ 'mp-nav-active': activePage === n.id }"
                    @click="goTo(n.id)"
                >
                    <i :class="n.icon" class="mp-nav-ico"></i>
                    <span class="mp-nav-lbl">{{ n.label }}</span>
                    <span v-if="n.badge" class="mp-nav-badge">{{ n.badge }}</span>
                </button>
            </nav>

            <div class="mp-settings">
                <div class="mp-settings-title">EMS Settings</div>
                <button class="mp-settings-item" :class="{ 'mp-settings-active': activePage === 'events' }" @click="goTo('events')">
                    <i class="bx bx-calendar mp-nav-ico"></i>
                    <span>Events</span>
                </button>
                <button class="mp-settings-item" :class="{ 'mp-settings-active': activePage === 'venues' }" @click="goTo('venues')">
                    <i class="bx bx-map mp-nav-ico"></i>
                    <span>Venues</span>
                </button>
                <button class="mp-settings-item" :class="{ 'mp-settings-active': activePage === 'functional-areas' }" @click="goTo('functional-areas')">
                    <i class="bx bx-layer mp-nav-ico"></i>
                    <span>Functional Areas</span>
                </button>
            </div>

            <div class="mp-settings">
                <div class="mp-settings-title">Security</div>
                <button class="mp-settings-item" :class="{ 'mp-settings-active': activePage === 'permissions' }" @click="goTo('permissions')">
                    <i class="bx bx-key mp-nav-ico"></i>
                    <span>Permissions</span>
                </button>
                <button class="mp-settings-item" :class="{ 'mp-settings-active': activePage === 'roles' }" @click="goTo('roles')">
                    <i class="bx bx-shield mp-nav-ico"></i>
                    <span>Roles</span>
                </button>
                <button class="mp-settings-item" :class="{ 'mp-settings-active': activePage === 'roles-permissions' }" @click="goTo('roles-permissions')">
                    <i class="bx bx-lock-open mp-nav-ico"></i>
                    <span>Roles &amp; Permissions</span>
                </button>
                <button class="mp-settings-item" :class="{ 'mp-settings-active': activePage === 'users' }" @click="goTo('users')">
                    <i class="bx bx-group mp-nav-ico"></i>
                    <span>Users</span>
                </button>
            </div>

            <div class="mp-foot" v-click-outside="() => evtDropdownOpen = false">
                <div class="mp-foot-lbl">Active event</div>

                <!-- Event card — click to open selector -->
                <div class="mp-foot-evt mp-foot-evt-btn" @click="evtDropdownOpen = !evtDropdownOpen">
                    <div class="mp-foot-evtcode">{{ activeEvent.code }}</div>
                    <div style="flex:1; min-width:0;">
                        <div class="mp-foot-evtname">
                            {{ activeEvent.name }}
                            <svg :style="{ transform: evtDropdownOpen ? 'rotate(180deg)' : '' }" style="transition:transform .18s; flex-shrink:0; margin-left:3px; vertical-align:middle" viewBox="0 0 24 24" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6"/></svg>
                        </div>
                        <div v-if="activeEvent.window" class="mp-foot-evtwin">{{ activeEvent.window }}</div>
                    </div>
                </div>

                <!-- Dropdown — floats above the card -->
                <div v-if="evtDropdownOpen" class="mp-evt-menu">
                    <button
                        v-for="e in availableEvents" :key="e.id"
                        class="mp-evt-opt"
                        :class="{ 'mp-evt-opt-active': e.id === selectedEventId }"
                        @click="selectEvent(e.id)"
                    >{{ e.name }}</button>
                </div>

                <div v-if="activeEvent.daysOut" class="mp-foot-count">
                    <span>T-{{ activeEvent.daysOut }} days</span>
                    <span class="mp-foot-bar"><span style="width:86%"/></span>
                </div>
            </div>
        </aside>

        <!-- ── Right column: topbar + main ───────────────────── -->
        <div class="mp-app-main">

            <!-- ── Topbar ──────────────────────────────────────── -->
            <header class="mp-topbar">
                <div class="tb-crumb">
                    <span class="tb-evt">{{ activeEvent.code }}</span>
                    <span class="tb-sep">/</span>
                    <span class="tb-here">{{ currentPageLabel }}</span>
                </div>
                <div class="tb-search">
                    <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5" stroke-linecap="round"/></svg>
                    <input placeholder="Search requests, items, sites… (⌘K)" />
                    <span class="tb-kbd">⌘K</span>
                </div>
                <div class="tb-right">
                    <button class="tb-icobtn" title="Notifications">
                        <svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"><path d="M15 17h5l-1.4-1.4A2 2 0 0118 14.2V11a6 6 0 10-12 0v3.2a2 2 0 01-.6 1.4L4 17h5m6 0a3 3 0 11-6 0"/></svg>
                        <span class="tb-icobtn-dot"></span>
                    </button>
                    <button class="tb-user">
                        <span class="tb-avatar">AR</span>
                        <div class="tb-user-meta">
                            <div class="tb-user-name">Amal Rashid</div>
                            <div class="tb-user-role">Venue Planner · MET</div>
                        </div>
                        <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" class="tb-user-chev"><path d="M6 9l6 6 6-6"/></svg>
                    </button>
                </div>
            </header>

            <!-- ── Main area ───────────────────────────────────── -->
            <div class="mp-main">

                <DashboardView
                    v-if="activePage === 'dash'"
                    :event="event"
                    :domains="domains"
                    :requests="requests"
                    :statuses="statuses"
                    :venues="venues"
                    :people="people"
                    @open-request="openRequest"
                    @go-to="goTo"
                />

                <RequestsView
                    v-else-if="activePage === 'requests'"
                    :requests="requests"
                    :domains="domains"
                    :venues="venues"
                    :statuses="statuses"
                    :people="people"
                    :approval-only="false"
                    @open-request="openRequest"
                    @go-to="goTo"
                />

                <ApprovalsView
                    v-else-if="activePage === 'approvals'"
                    :requests="requests"
                    :domains="domains"
                    :venues="venues"
                    :statuses="statuses"
                    :people="people"
                    @open-request="openRequest"
                    @go-to="goTo"
                />

                <NewRequestView
                    v-else-if="activePage === 'new'"
                    :key="'new-' + (prefilledSku ?? 'blank')"
                    :domains="domains"
                    :venues="venues"
                    :catalog="catalog"
                    :event="activeEvent"
                    :people="people"
                    :prefill-sku="prefilledSku"
                />

                <CatalogView
                    v-else-if="activePage === 'catalog'"
                    :catalog="catalog"
                    :domains="domains"
                    :event="activeEvent"
                    @go-to="goTo"
                />

                <ReportsView
                    v-else-if="activePage === 'reports'"
                    :domains="domains"
                    :requests="requests"
                    :event="event"
                />

                <DetailView
                    v-else-if="activePage === 'detail'"
                    :requests="requests"
                    :detail-id="detailId"
                    :domains="domains"
                    :venues="venues"
                    :statuses="statuses"
                    :people="people"
                    @go-to="goTo"
                />

                <EventsView           v-else-if="activePage === 'events'" />
                <VenuesView           v-else-if="activePage === 'venues'" />
                <FunctionalAreasView  v-else-if="activePage === 'functional-areas'" />
                <PermissionsView      v-else-if="activePage === 'permissions'" />
                <RolesView            v-else-if="activePage === 'roles'" />
                <RolesPermissionsView v-else-if="activePage === 'roles-permissions'" />
                <UsersView            v-else-if="activePage === 'users'" />

            </div><!-- /.mp-main -->
        </div><!-- /.mp-app-main -->
    </div><!-- /.mp-shell -->
</template>

<style scoped>
/* ── Shell layout ─────────────────────────────────────────────────────────── */
.mp-shell {
    display: flex;
    gap: 0;
    height: 100vh;
    background: #f6f5f1;
    overflow: hidden;
}

/* ── MP Sidebar ───────────────────────────────────────────────────────────── */
.mp-sidebar {
    width: 220px;
    flex-shrink: 0;
    background: #fff;
    border-right: 1px solid #e8e4db;
    display: flex;
    flex-direction: column;
    overflow-y: auto;
    height: 100vh;
}
.mp-brand {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 18px 16px 14px;
    border-bottom: 1px solid #e8e4db;
}
.mp-mark {
    width: 36px; height: 36px;
    background: #1a1614;
    color: #fff;
    border-radius: 8px;
    display: flex; align-items: center; justify-content: center;
    font-size: 14px; font-weight: 700;
    flex-shrink: 0;
}
.mp-brandname { font-size: 14px; font-weight: 700; color: #1a1614; line-height: 1.2; }
.mp-plus { color: #0f766e; }
.mp-brandsub { font-size: 10px; color: #76706a; letter-spacing: .04em; }

.mp-nav { padding: 8px 8px; flex: 1; }
.mp-nav-item {
    width: 100%; display: flex; align-items: center; gap: 9px;
    padding: 8px 10px;
    border: none; background: none; cursor: pointer;
    border-radius: 7px;
    font-size: 13px; color: #545a6d;
    text-align: left; margin-bottom: 2px;
    transition: background .15s, color .15s;
}
.mp-nav-item:hover { background: #f6f5f1; color: #1a1614; }
.mp-nav-active { background: rgba(15,118,110,.12) !important; color: #0f766e !important; font-weight: 600; }
.mp-nav-ico { font-size: 17px; opacity: .85; }
.mp-nav-lbl { flex: 1; }
.mp-nav-badge {
    background: #0f766e; color: #fff;
    font-size: 10px; font-weight: 700;
    border-radius: 20px; padding: 1px 6px;
}

.mp-settings {
    padding: 8px 8px 4px;
    border-top: 1px solid #e8e4db;
}
.mp-settings-title {
    font-size: 10px; font-weight: 700; letter-spacing: .06em;
    text-transform: uppercase; color: #76706a;
    padding: 6px 10px 4px;
}
.mp-settings-item {
    display: flex; align-items: center; gap: 10px;
    padding: 7px 10px; border-radius: 6px;
    font-size: 13px; color: #1a1614; text-decoration: none;
    transition: background .15s;
    width: 100%; border: none; background: none; cursor: pointer; text-align: left;
}
.mp-settings-item:hover { background: #f6f5f1; color: #1a1614; }
.mp-settings-active { background: rgba(15,118,110,.12) !important; color: #0f766e !important; font-weight: 600; }
.mp-foot {
    padding: 14px 14px 16px;
    border-top: 1px solid #e8e4db;
    font-size: 11px; color: #76706a;
}
.mp-foot-lbl { text-transform: uppercase; letter-spacing: .06em; margin-bottom: 6px; }
.mp-foot { position: relative; }
.mp-foot-evt-btn {
    cursor: pointer; border-radius: 6px;
    transition: background .12s;
    margin: 0 -6px; padding: 4px 6px;
}
.mp-foot-evt-btn:hover { background: #f3f0e8; }
.mp-evt-menu {
    position: absolute; bottom: calc(100% + 4px); left: 0; right: 0;
    background: #fff; border: 1px solid #e8e4db; border-radius: 8px;
    box-shadow: 0 4px 18px rgba(0,0,0,.10);
    padding: 4px; z-index: 200; max-height: 220px; overflow-y: auto;
}
.mp-evt-opt {
    display: block; width: 100%; text-align: left;
    padding: 7px 10px; border: none; border-radius: 5px;
    background: transparent; font-size: 12.5px; color: #3d3833;
    cursor: pointer; transition: background .12s;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.mp-evt-opt:hover { background: #f6f5f1; }
.mp-evt-opt-active { background: rgba(15,118,110,.1) !important; color: #0f766e; font-weight: 600; }
.mp-foot-evt { display: flex; gap: 8px; align-items: flex-start; margin-bottom: 8px; }
.mp-foot-evtcode { font-size: 11px; font-weight: 700; color: #0f766e; font-family: monospace; padding-top: 1px; }
.mp-foot-evtname { font-weight: 600; color: #1a1614; font-size: 12px; }
.mp-foot-evtwin { font-size: 10px; color: #76706a; }
.mp-foot-count { display: flex; align-items: center; gap: 8px; font-size: 11px; }
.mp-foot-bar { flex: 1; height: 4px; background: #e8e4db; border-radius: 2px; overflow: hidden; }
.mp-foot-bar span { display: block; height: 100%; background: #0f766e; border-radius: 2px; }

/* ── Main area ────────────────────────────────────────────────────────────── */
.mp-app-main { display: flex; flex-direction: column; flex: 1; min-width: 0; height: 100vh; }

/* ── Topbar ───────────────────────────────────────────────────────────────── */
.mp-topbar {
    display: flex; align-items: center; gap: 16px;
    padding: 12px 28px;
    background: #fff;
    border-bottom: 1px solid #e8e4db;
    height: 56px; flex-shrink: 0;
}
.tb-crumb { display: flex; align-items: center; gap: 8px; font-size: 13px; }
.tb-evt {
    background: #1a1614; color: #fff;
    font-family: ui-monospace, monospace; font-size: 10.5px; font-weight: 600;
    padding: 3px 6px; border-radius: 4px; letter-spacing: .04em;
}
.tb-sep { color: #a39d96; }
.tb-here { font-weight: 500; color: #1a1614; }
.tb-search {
    flex: 1; max-width: 520px;
    display: flex; align-items: center; gap: 8px;
    background: #fff; border: 1px solid #e8e4db;
    border-radius: 7px; padding: 6px 10px; color: #76706a;
}
.tb-search input { border: 0; outline: none; background: transparent; flex: 1; font-size: 13px; color: #1a1614; }
.tb-kbd {
    font-family: ui-monospace, monospace; font-size: 10.5px;
    background: #f3f0e8; color: #76706a;
    padding: 1px 6px; border-radius: 4px; letter-spacing: .04em;
}
.tb-right { display: flex; align-items: center; gap: 6px; margin-left: auto; }
.tb-icobtn {
    width: 34px; height: 34px; border-radius: 7px;
    border: 1px solid #e8e4db; background: #fff; color: #3d3833;
    display: grid; place-items: center; cursor: pointer; position: relative;
}
.tb-icobtn:hover { background: #fbfaf6; }
.tb-icobtn-dot {
    position: absolute; top: 7px; right: 7px;
    width: 7px; height: 7px;
    background: #0f766e; border-radius: 50%; border: 2px solid #fff;
}
.tb-user {
    display: flex; align-items: center; gap: 8px;
    padding: 4px 8px 4px 4px;
    background: #fff; border: 1px solid #e8e4db;
    border-radius: 7px; cursor: pointer;
}
.tb-user:hover { background: #fbfaf6; }
.tb-avatar {
    display: inline-grid; place-items: center;
    width: 26px; height: 26px; border-radius: 50%;
    background: #0f766e; color: #fff;
    font-size: 11px; font-weight: 600;
}
.tb-user-meta { line-height: 1.15; text-align: left; }
.tb-user-name { font-size: 12.5px; font-weight: 600; color: #1a1614; }
.tb-user-role { font-size: 10.5px; color: #76706a; }
.tb-user-chev { color: #76706a; }

.mp-main { flex: 1; overflow-y: auto; overflow-x: hidden; padding: 12px 20px; }

/* ── Sidebar collapse ─────────────────────────────────────────────────────── */
.mp-sidebar {
    transition: width .22s cubic-bezier(.4,0,.2,1);
    overflow-x: hidden;
}
.mp-sidebar-collapsed { width: 58px; }

/* Toggle button */
.mp-toggle-btn {
    margin-left: auto; flex-shrink: 0;
    width: 22px; height: 22px; border-radius: 5px;
    border: 1px solid #e8e4db; background: transparent;
    cursor: pointer; display: flex; align-items: center; justify-content: center;
    color: #76706a; transition: background .15s, color .15s;
}
.mp-toggle-btn:hover { background: #f6f5f1; color: #1a1614; }
.mp-toggle-btn svg { transition: transform .22s cubic-bezier(.4,0,.2,1); }
.mp-sidebar-collapsed .mp-toggle-btn { margin-left: 0; }
.mp-sidebar-collapsed .mp-toggle-btn svg { transform: rotate(180deg); }

/* Brand in collapsed state */
.mp-sidebar-collapsed .mp-brand {
    flex-direction: column; align-items: center;
    gap: 6px; padding: 12px 8px;
}
.mp-sidebar-collapsed .mp-brandtxt { display: none; }

/* Nav items in collapsed state */
.mp-sidebar-collapsed .mp-nav-item { justify-content: center; padding: 10px; }
.mp-sidebar-collapsed .mp-nav-lbl,
.mp-sidebar-collapsed .mp-nav-badge { display: none; }

/* Settings sections in collapsed state */
.mp-sidebar-collapsed .mp-settings { padding: 6px 6px 2px; }
.mp-sidebar-collapsed .mp-settings-title { display: none; }
.mp-sidebar-collapsed .mp-settings-item { justify-content: center; padding: 10px; }
.mp-sidebar-collapsed .mp-settings-item span { display: none; }

/* Footer hidden when collapsed */
.mp-sidebar-collapsed .mp-foot { display: none; }
</style>
