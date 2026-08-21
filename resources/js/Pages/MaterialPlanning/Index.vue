<script setup>
import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import axios from 'axios';

import DashboardView   from './views/DashboardView.vue';
import RequestsView    from './views/RequestsView.vue';
import ApprovalsView   from './views/ApprovalsView.vue';
import NewRequestView  from './views/NewRequestView.vue';
import CatalogView     from './views/CatalogView.vue';
import ServiceOptionsView from './views/ServiceOptionsView.vue';
import SupplierView       from './views/SupplierView.vue';
import DomainView         from './views/DomainView.vue';
import AreaView           from './views/AreaView.vue';
import SpaceView          from './views/SpaceView.vue';
import ItemGroupView      from './views/ItemGroupView.vue';
import ItemSubgroupView   from './views/ItemSubgroupView.vue';
import ChangeOrdersView   from './views/ChangeOrdersView.vue';
import ReportsView     from './views/ReportsView.vue';
import DetailView      from './views/DetailView.vue';
import EventsView            from './views/EventsView.vue';
import VenuesView            from './views/VenuesView.vue';
import FunctionalAreasView   from './views/FunctionalAreasView.vue';
import PermissionsView       from './views/PermissionsView.vue';
import RolesView             from './views/RolesView.vue';
import RolesPermissionsView  from './views/RolesPermissionsView.vue';
import UsersView             from './views/UsersView.vue';
import SettingsView          from './views/SettingsView.vue';

const props = defineProps({
    event:          Object,
    venues:         Array,
    domains:        Array,
    statuses:       Object,
    entityStatuses: Array,
    classifications: Array,
    people:         Array,
    requests:       Array,
    catalog:        Array,
    suppliers:      Array,
    areas:          Array,
    spaces:         Array,
    itemGroups:     Array,
    itemSubgroups:  Array,
    serviceOptions: Array,
    changeOrders:   Array,
    coStates:       Object,
    permissions:    Object,
    functionalAreas: { type: Array, default: () => [] },
});

// ── Event selector ──────────────────────────────────────────────────────────
// Active event is resolved server-side from the PHP session (see
// MaterialPlanningController::index()/setActiveEvent()), so the selection
// persists across reloads and devices instead of living in localStorage.
// Declared up here (ahead of Navigation) because the nav badge counts below
// need to react to whichever event is currently active.
const availableEvents = ref([]);
const selectedEventId = ref(props.event?.id ?? null);

async function persistActiveEvent(id) {
    try {
        await axios.put(route('material-planning.active-event.update'), { event_id: id });
    } catch (_) {}
}

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

// ── Navigation ─────────────────────────────────────────────────────────────
const activePage       = ref('dash');
const detailId         = ref(null);
const sidebarCollapsed = ref(false);

// Badge counts are scoped to the active event and derived from the same
// requests/changeOrders data the list views use, instead of being hardcoded.
const navEventRequests = computed(() =>
    activeEvent.value?.id ? props.requests.filter(r => r.eventId === activeEvent.value.id) : props.requests
);
const navEventChangeOrders = computed(() =>
    activeEvent.value?.id ? props.changeOrders.filter(co => co.eventId === activeEvent.value.id) : props.changeOrders
);

const nav = computed(() => {
    const draftCount = navEventRequests.value.filter(r => r.status === 'draft').length;
    const approvalCount = navEventRequests.value.filter(r => ['submitted', 'l1', 'l2', 'finance', 'changed'].includes(r.status)).length;
    const changeOrderCount = navEventChangeOrders.value.filter(co => co.state === 'pending').length;

    return [
        { id: 'dash',      label: 'Dashboard',    icon: 'bx bx-grid-alt',       badge: null },
        { id: 'requests',  label: 'Requests',     icon: 'bx bx-list-ul',         badge: draftCount ? String(draftCount) : null },
        { id: 'new',       label: 'New Request',  icon: 'bx bx-plus-circle',     badge: null },
        { id: 'catalog',   label: 'Catalog',      icon: 'bx bx-book-open',       badge: null },
        { id: 'options',   label: 'Service Options', icon: 'bx bx-purchase-tag', badge: null },
        { id: 'approvals', label: 'Approvals',    icon: 'bx bx-check-shield',    badge: approvalCount ? String(approvalCount) : null },
        { id: 'changes',   label: 'Change Orders', icon: 'bx bx-git-compare',    badge: changeOrderCount ? String(changeOrderCount) : null },
        { id: 'reports',   label: 'Reports',      icon: 'bx bx-bar-chart-alt-2', badge: null },
        { id: 'settings',  label: 'Settings',     icon: 'bx bx-cog',             badge: null },
    ];
});

const appSetupsNav = [
    { id: 'suppliers',       label: 'Suppliers',       icon: 'bx bx-store' },
    { id: 'domains',         label: 'Domains',         icon: 'bx bx-shape-square' },
    { id: 'areas',           label: 'Areas',           icon: 'bx bx-buildings' },
    { id: 'spaces',          label: 'Spaces',          icon: 'bx bx-map-pin' },
    { id: 'item-groups',     label: 'Item Groups',     icon: 'bx bx-collection' },
    { id: 'item-subgroups',  label: 'Item Subgroups',  icon: 'bx bx-list-plus' },
];

const openSections = ref({ appSetups: false, ems: false, security: false });
function toggleSection(key) { openSections.value[key] = !openSections.value[key]; }

const sectionOf = {
    ...Object.fromEntries(appSetupsNav.map(n => [n.id, 'appSetups'])),
    events: 'ems', venues: 'ems', 'functional-areas': 'ems',
    permissions: 'security', roles: 'security', 'roles-permissions': 'security', users: 'security',
};
watch(activePage, id => {
    const section = sectionOf[id];
    if (section) openSections.value[section] = true;
});

const detailData    = ref(null);
const detailLoading = ref(false);
const detailError   = ref(null);

async function openRequest(id) {
    detailId.value = id;
    activePage.value = 'detail';
    detailData.value = null;
    detailError.value = null;
    detailLoading.value = true;
    try {
        const { data } = await axios.get(route('mp.requests.show', id));
        detailData.value = data;
    } catch (e) {
        detailError.value = e.response?.data?.message || 'Failed to load request details.';
    } finally {
        detailLoading.value = false;
    }
}
const prefilledSku = ref(null);
const editRequestCode = ref(null);
function goTo(id, payload = null) {
    // App Setups / EMS Settings / Security are admin-only — even with the sidebar
    // links hidden, `activePage` is plain client-side state (including URL-hash
    // restore), so refuse to land on these views for anyone else.
    if (sectionOf[id] && !props.permissions.isAdmin) id = 'dash';
    activePage.value = id;
    prefilledSku.value = (id === 'new' && payload?.sku) ? payload.sku : null;
    editRequestCode.value = (id === 'new' && payload?.editCode) ? payload.editCode : null;
}

// A request was created/submitted/draft-saved via NewRequestView — its accessors
// (items/qty/value/domain) are computed server-side, so refresh `requests` from the
// server rather than faking it client-side. Only navigate to the Requests list when
// the request was actually submitted — a draft save should keep the user on the form.
function onRequestSaved(navigate = true) {
    router.reload({ only: ['requests', 'people'], onFinish: () => { if (navigate) goTo('requests'); } });
}

// Requests were bulk-deleted, or the user clicked the refresh icon, in
// RequestsView/ApprovalsView — refresh in place.
const requestsRefreshing = ref(false);
function refreshRequests() {
    requestsRefreshing.value = true;
    router.reload({ only: ['requests'], onFinish: () => { requestsRefreshing.value = false; } });
}

// ── User menu ──────────────────────────────────────────────────────────────
const page = usePage();
const authUser = computed(() => page.props.auth?.user ?? { name: 'Amal Rashid', email: '' });
function initialsOf(name) {
    if (!name) return '—';
    const parts = name.trim().split(/\s+/);
    return ((parts[0]?.[0] || '') + (parts[1]?.[0] || '')).toUpperCase();
}

const userMenuOpen = ref(false);
const userMenuRef = ref(null);
const availability = ref('active');

function closeUserMenu(e) {
    if (userMenuOpen.value && userMenuRef.value && !userMenuRef.value.contains(e.target)) {
        userMenuOpen.value = false;
    }
}
onMounted(() => document.addEventListener('click', closeUserMenu));
onBeforeUnmount(() => document.removeEventListener('click', closeUserMenu));

// The "Switch" button in the user menu's venue card drives the active-event
// selection (same availableEvents/selectEvent used by the rest of the page).
const userEvtOpen = ref(false);
watch(userMenuOpen, open => { if (!open) userEvtOpen.value = false; });

function goToFromMenu(id) {
    userMenuOpen.value = false;
    goTo(id);
}
function signOut() {
    router.post(route('logout'));
}

const sectionLabels = {
    events: 'Events', venues: 'Venues', 'functional-areas': 'Functional Areas',
    permissions: 'Permissions', roles: 'Roles', 'roles-permissions': 'Roles & Permissions', users: 'Users',
};
const currentPageLabel = computed(() =>
    nav.value.find(n => n.id === activePage.value)?.label
    || appSetupsNav.find(n => n.id === activePage.value)?.label
    || sectionLabels[activePage.value]
    || ''
);

// ── URL sync — keeps the active view (and open request) across a page refresh ──
const validPages = new Set([
    ...nav.value.map(n => n.id),
    ...appSetupsNav.map(n => n.id),
    'events', 'venues', 'functional-areas',
    'permissions', 'roles', 'roles-permissions', 'users',
]);

watch([activePage, detailId], () => {
    const hash = '#' + (activePage.value === 'detail' && detailId.value
        ? `detail/${encodeURIComponent(detailId.value)}`
        : activePage.value);
    if (window.location.hash !== hash) {
        history.replaceState(null, '', hash);
    }
});

function restoreFromHash() {
    const raw = window.location.hash.replace(/^#/, '');
    if (!raw) return;
    if (raw.startsWith('detail/')) {
        const id = decodeURIComponent(raw.slice('detail/'.length));
        if (id) openRequest(id);
        return;
    }
    if (validPages.has(raw) && !(sectionOf[raw] && !props.permissions.isAdmin)) activePage.value = raw;
}

// ── Workspace settings ────────────────────────────────────────────────────────
// Backed by the `settings` DB table and cached in the PHP session (shared into
// every Inertia response via HandleInertiaRequests) — not client-side storage.
const approvalsEnabled = ref(page.props.settings?.approvalsEnabled ?? true);
const approvalsEnabledSaving = ref(false);
async function setApprovalsEnabled(enabled) {
    const previous = approvalsEnabled.value;
    approvalsEnabled.value = enabled;
    approvalsEnabledSaving.value = true;
    try {
        await axios.put(route('settings.approvals-enabled.update'), { enabled });
    } catch (e) {
        approvalsEnabled.value = previous;
    } finally {
        approvalsEnabledSaving.value = false;
    }
}

const showItemValues = ref(page.props.settings?.showItemValues ?? false);
const showItemValuesSaving = ref(false);
async function setShowItemValues(enabled) {
    const previous = showItemValues.value;
    showItemValues.value = enabled;
    showItemValuesSaving.value = true;
    try {
        await axios.put(route('settings.show-item-values.update'), { enabled });
    } catch (e) {
        showItemValues.value = previous;
    } finally {
        showItemValuesSaving.value = false;
    }
}

function selectEvent(id) {
    selectedEventId.value = id;
    userEvtOpen.value = false;
    persistActiveEvent(id);
}

const vClickOutside = {
    mounted(el, b)   { el._co = e => { if (!el.contains(e.target)) b.value(e); }; document.addEventListener('click', el._co); },
    unmounted(el)    { document.removeEventListener('click', el._co); },
};

onMounted(async () => {
    restoreFromHash();

    try {
        const { data } = await axios.get(route('events.data'), { params: { limit: 100, offset: 0 } });
        availableEvents.value = data.rows ?? data ?? [];
        if (!selectedEventId.value && availableEvents.value.length) {
            selectedEventId.value = availableEvents.value[0].id;
            persistActiveEvent(selectedEventId.value);
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

            <div v-if="permissions.isAdmin" class="mp-settings">
                <button class="mp-settings-title mp-settings-title-btn" @click="toggleSection('appSetups')">
                    <span>App Setups</span>
                    <svg class="mp-settings-chev" :class="{ 'mp-settings-chev-open': openSections.appSetups }" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6"/></svg>
                </button>
                <div class="mp-settings-items" v-show="sidebarCollapsed || openSections.appSetups">
                    <button
                        v-for="n in appSetupsNav"
                        :key="n.id"
                        class="mp-settings-item"
                        :class="{ 'mp-settings-active': activePage === n.id }"
                        @click="goTo(n.id)"
                    >
                        <i :class="n.icon" class="mp-nav-ico"></i>
                        <span>{{ n.label }}</span>
                    </button>
                </div>
            </div>

            <div v-if="permissions.isAdmin" class="mp-settings">
                <button class="mp-settings-title mp-settings-title-btn" @click="toggleSection('ems')">
                    <span>EMS Settings</span>
                    <svg class="mp-settings-chev" :class="{ 'mp-settings-chev-open': openSections.ems }" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6"/></svg>
                </button>
                <div class="mp-settings-items" v-show="sidebarCollapsed || openSections.ems">
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
            </div>

            <div v-if="permissions.isAdmin" class="mp-settings">
                <button class="mp-settings-title mp-settings-title-btn" @click="toggleSection('security')">
                    <span>Security</span>
                    <svg class="mp-settings-chev" :class="{ 'mp-settings-chev-open': openSections.security }" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6"/></svg>
                </button>
                <div class="mp-settings-items" v-show="sidebarCollapsed || openSections.security">
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
                <div class="tb-event-display">
                    <i class="bx bx-calendar-event"></i>
                    <span class="tb-event-name">{{ activeEvent.name }}</span>
                    <span v-if="activeEvent.window" class="tb-event-window">{{ activeEvent.window }}</span>
                </div>
                <div class="tb-right">
                    <button class="tb-icobtn" title="Notifications">
                        <svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"><path d="M15 17h5l-1.4-1.4A2 2 0 0118 14.2V11a6 6 0 10-12 0v3.2a2 2 0 01-.6 1.4L4 17h5m6 0a3 3 0 11-6 0"/></svg>
                        <span class="tb-icobtn-dot"></span>
                    </button>
                    <div class="tb-user-wrap" ref="userMenuRef">
                        <button class="tb-user" @click="userMenuOpen = !userMenuOpen">
                            <span class="tb-avatar">{{ initialsOf(authUser.name) }}</span>
                            <div class="tb-user-meta">
                                <div class="tb-user-name">{{ authUser.name }}</div>
                                <div class="tb-user-role">Venue Planner · MET</div>
                            </div>
                            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" class="tb-user-chev" :class="{ 'tb-user-chev-open': userMenuOpen }"><path d="M6 9l6 6 6-6"/></svg>
                        </button>

                        <div v-if="userMenuOpen" class="tb-user-menu">
                            <div class="tbm-profile">
                                <span class="tbm-avatar">{{ initialsOf(authUser.name) }}</span>
                                <div class="tbm-profile-meta">
                                    <div class="tbm-name">{{ authUser.name }}</div>
                                    <div class="tbm-role">Venue Planner</div>
                                    <div class="tbm-email"><span class="tbm-dot"/>{{ authUser.email }}</div>
                                </div>
                            </div>

                            <div class="tbm-venue">
                                <div class="tbm-venue-top">
                                    <span class="tbm-badge">{{ activeEvent.code }}</span>
                                    <div class="tbm-switch-wrap" v-click-outside="() => userEvtOpen = false">
                                        <button class="tbm-switch" type="button" @click="userEvtOpen = !userEvtOpen">Switch</button>
                                        <div v-if="userEvtOpen" class="tbm-evt-menu">
                                            <button
                                                v-for="e in availableEvents" :key="e.id"
                                                class="mp-evt-opt"
                                                :class="{ 'mp-evt-opt-active': e.id === selectedEventId }"
                                                @click="selectEvent(e.id)"
                                            >{{ e.name }}</button>
                                        </div>
                                    </div>
                                </div>
                                <div class="tbm-venue-name">{{ activeEvent.name }}</div>
                                <div class="tbm-venue-sub">{{ activeEvent.window || 'Active event' }}</div>
                            </div>

                            <div class="tbm-section">
                                <div class="tbm-section-lbl">Availability</div>
                                <div class="tbm-avail">
                                    <button class="tbm-avail-btn" :class="{ 'tbm-avail-on': availability === 'active' }" @click="availability = 'active'">
                                        <span class="tbm-avail-dot" style="background:#16a34a"/>Active
                                    </button>
                                    <button class="tbm-avail-btn" :class="{ 'tbm-avail-on': availability === 'heads-down' }" @click="availability = 'heads-down'">
                                        <span class="tbm-avail-dot" style="background:#b45309"/>Heads-down
                                    </button>
                                    <button class="tbm-avail-btn" :class="{ 'tbm-avail-on': availability === 'out-of-office' }" @click="availability = 'out-of-office'">
                                        <span class="tbm-avail-dot" style="background:#9ca3af"/>Out of office
                                    </button>
                                </div>
                            </div>

                            <div class="tbm-list">
                                <button class="tbm-item" @click="goToFromMenu('requests')">
                                    <span>My requests</span><span class="tbm-count">14</span>
                                </button>
                                <button class="tbm-item" @click="goToFromMenu('approvals')">
                                    <span>Awaiting my approval</span><span class="tbm-count tbm-count-warn">3</span>
                                </button>
                                <button class="tbm-item">
                                    <span>Watching</span><span class="tbm-count">11</span>
                                </button>
                                <button class="tbm-item">
                                    <span>Downloads &amp; exports</span>
                                </button>
                            </div>

                            <div class="tbm-list">
                                <button class="tbm-item"><span>Preferences</span></button>
                                <button class="tbm-item">
                                    <span>Keyboard shortcuts</span><span class="tbm-kbd">⌘ /</span>
                                </button>
                                <button class="tbm-item"><span>Help &amp; docs</span></button>
                            </div>

                            <button class="tbm-signout" @click="signOut">Sign out <span>→</span></button>
                        </div>
                    </div>
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
                    :functional-areas="functionalAreas"
                    :refreshing="requestsRefreshing"
                    :show-item-values="showItemValues"
                    :event="activeEvent"
                    :approval-only="false"
                    @open-request="openRequest"
                    @go-to="goTo"
                    @requests-deleted="refreshRequests"
                    @refresh="refreshRequests"
                />

                <ApprovalsView
                    v-else-if="activePage === 'approvals'"
                    :requests="requests"
                    :domains="domains"
                    :venues="venues"
                    :statuses="statuses"
                    :people="people"
                    :functional-areas="functionalAreas"
                    :refreshing="requestsRefreshing"
                    :show-item-values="showItemValues"
                    :event="activeEvent"
                    @open-request="openRequest"
                    @go-to="goTo"
                    @requests-deleted="refreshRequests"
                    @refresh="refreshRequests"
                />

                <NewRequestView
                    v-else-if="activePage === 'new'"
                    :key="'new-' + (editRequestCode ?? prefilledSku ?? 'blank')"
                    :domains="domains"
                    :venues="venues"
                    :catalog="catalog"
                    :areas="areas"
                    :spaces="spaces"
                    :event="activeEvent"
                    :people="people"
                    :prefill-sku="prefilledSku"
                    :edit-request-code="editRequestCode"
                    :approvals-enabled="approvalsEnabled"
                    :functional-areas="functionalAreas"
                    :requests="requests"
                    :show-item-values="showItemValues"
                    @go-to="goTo"
                    @request-saved="onRequestSaved"
                />

                <CatalogView
                    v-else-if="activePage === 'catalog'"
                    :catalog="catalog"
                    :domains="domains"
                    :suppliers="suppliers"
                    :service-options="serviceOptions"
                    :event="activeEvent"
                    @go-to="goTo"
                />

                <ServiceOptionsView
                    v-else-if="activePage === 'options'"
                    :catalog="catalog"
                    :domains="domains"
                    :suppliers="suppliers"
                    :service-options="serviceOptions"
                    :people="people"
                    :classifications="classifications"
                    :event="activeEvent"
                />

                <SupplierView
                    v-else-if="activePage === 'suppliers'"
                    :suppliers="suppliers"
                    :classifications="classifications"
                    :statuses="entityStatuses"
                    :permissions="permissions"
                    :event="activeEvent"
                />

                <DomainView
                    v-else-if="activePage === 'domains'"
                    :domains="domains"
                    :statuses="entityStatuses"
                    :permissions="permissions"
                    :event="activeEvent"
                />

                <AreaView
                    v-else-if="activePage === 'areas'"
                    :areas="areas"
                    :statuses="entityStatuses"
                    :permissions="permissions"
                    :event="activeEvent"
                />

                <SpaceView
                    v-else-if="activePage === 'spaces'"
                    :spaces="spaces"
                    :areas="areas"
                    :statuses="entityStatuses"
                    :permissions="permissions"
                    :event="activeEvent"
                />

                <ItemGroupView
                    v-else-if="activePage === 'item-groups'"
                    :item-groups="itemGroups"
                    :domains="domains"
                    :statuses="entityStatuses"
                    :permissions="permissions"
                    :event="activeEvent"
                />

                <ItemSubgroupView
                    v-else-if="activePage === 'item-subgroups'"
                    :item-subgroups="itemSubgroups"
                    :item-groups="itemGroups"
                    :statuses="entityStatuses"
                    :permissions="permissions"
                    :event="activeEvent"
                />

                <ChangeOrdersView
                    v-else-if="activePage === 'changes'"
                    :change-orders="changeOrders"
                    :co-states="coStates"
                    :requests="requests"
                    :domains="domains"
                    :venues="venues"
                    :people="people"
                    :event="activeEvent"
                    @open-request="openRequest"
                />

                <ReportsView
                    v-else-if="activePage === 'reports'"
                    :domains="domains"
                    :requests="requests"
                    :event="event"
                />

                <SettingsView
                    v-else-if="activePage === 'settings'"
                    :event="activeEvent"
                    :approvals-enabled="approvalsEnabled"
                    :approvals-enabled-saving="approvalsEnabledSaving"
                    :show-item-values="showItemValues"
                    :show-item-values-saving="showItemValuesSaving"
                    @update:approvals-enabled="setApprovalsEnabled"
                    @update:show-item-values="setShowItemValues"
                    @go-to="goTo"
                />

                <DetailView
                    v-else-if="activePage === 'detail'"
                    :requests="requests"
                    :detail-id="detailId"
                    :detail-data="detailData"
                    :detail-loading="detailLoading"
                    :detail-error="detailError"
                    :domains="domains"
                    :venues="venues"
                    :statuses="statuses"
                    :people="people"
                    :catalog="catalog"
                    :suppliers="suppliers"
                    :service-options="serviceOptions"
                    :permissions="permissions"
                    :show-item-values="showItemValues"
                    @go-to="goTo"
                    @refresh-detail="() => openRequest(detailId)"
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
.mp-settings-title-btn {
    display: flex; align-items: center; justify-content: space-between;
    width: 100%; border: none; background: none; cursor: pointer;
    border-radius: 6px; transition: background .15s, color .15s;
}
.mp-settings-title-btn:hover { background: #f6f5f1; color: #3d3833; }
.mp-settings-chev { flex-shrink: 0; transition: transform .18s ease; transform: rotate(-90deg); margin-right: 6px; }
.mp-settings-chev-open { transform: rotate(0deg); }
.mp-settings-items { overflow: hidden; }
.mp-settings-item {
    display: flex; align-items: center; gap: 10px;
    padding: 7px 10px; border-radius: 6px;
    font-size: 13px; color: #1a1614; text-decoration: none;
    transition: background .15s;
    width: 100%; border: none; background: none; cursor: pointer; text-align: left;
}
.mp-settings-item:hover { background: #f6f5f1; color: #1a1614; }
.mp-settings-active { background: rgba(15,118,110,.12) !important; color: #0f766e !important; font-weight: 600; }
.mp-evt-opt {
    display: block; width: 100%; text-align: left;
    padding: 7px 10px; border: none; border-radius: 5px;
    background: transparent; font-size: 12.5px; color: #3d3833;
    cursor: pointer; transition: background .12s;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.mp-evt-opt:hover { background: #f6f5f1; }
.mp-evt-opt-active { background: rgba(15,118,110,.1) !important; color: #0f766e; font-weight: 600; }

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
.tb-event-display {
    flex: 1; max-width: 520px;
    display: flex; align-items: center; gap: 8px;
    background: #fbfaf6; border: 1px solid #e8e4db;
    border-radius: 7px; padding: 7px 12px; color: #0f766e;
    font-size: 16px;
}
.tb-event-name { font-size: 13px; font-weight: 600; color: #1a1614; }
.tb-event-window { font-size: 11.5px; color: #76706a; margin-left: auto; }
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
.tb-user-chev { color: #76706a; transition: transform .15s; }
.tb-user-chev-open { transform: rotate(180deg); }

.tb-user-wrap { position: relative; }
.tb-user-menu {
    position: absolute; top: calc(100% + 8px); right: 0;
    width: 300px; background: #fff; border: 1px solid #e8e4db;
    border-radius: 12px; box-shadow: 0 12px 32px rgba(26,22,20,.14);
    padding: 14px; z-index: 50;
}
.tbm-profile { display: flex; gap: 10px; align-items: center; padding-bottom: 12px; margin-bottom: 12px; border-bottom: 1px solid #f3f0ea; }
.tbm-avatar {
    display: inline-grid; place-items: center;
    width: 40px; height: 40px; border-radius: 50%;
    background: #7c2d12; color: #fff; font-size: 14px; font-weight: 700; flex-shrink: 0;
}
.tbm-profile-meta { min-width: 0; }
.tbm-name { font-size: 14px; font-weight: 700; color: #1a1614; }
.tbm-role { font-size: 11.5px; color: #76706a; margin-top: 1px; }
.tbm-email {
    display: flex; align-items: center; gap: 5px;
    font-size: 11.5px; color: #0f766e; margin-top: 3px;
    overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}
.tbm-dot { width: 6px; height: 6px; border-radius: 50%; background: #16a34a; flex-shrink: 0; }

.tbm-venue { background: #fbfaf6; border: 1px solid #e8e4db; border-radius: 8px; padding: 10px 12px; margin-bottom: 12px; }
.tbm-venue-top { display: flex; align-items: center; justify-content: space-between; margin-bottom: 6px; }
.tbm-badge {
    background: #1a1614; color: #fff;
    font-family: ui-monospace, monospace; font-size: 10px; font-weight: 600;
    padding: 2px 6px; border-radius: 4px; letter-spacing: .04em;
}
.tbm-switch-wrap { position: relative; }
.tbm-switch { border: 1px solid #e8e4db; background: #fff; border-radius: 6px; padding: 3px 10px; font-size: 11.5px; color: #1a1614; cursor: pointer; }
.tbm-switch:hover { background: #f6f5f1; }
.tbm-evt-menu {
    position: absolute; top: calc(100% + 4px); right: 0;
    width: 220px; background: #fff; border: 1px solid #e8e4db; border-radius: 8px;
    box-shadow: 0 4px 18px rgba(0,0,0,.10);
    padding: 4px; z-index: 60; max-height: 220px; overflow-y: auto;
}
.tbm-venue-name { font-size: 13px; font-weight: 600; color: #1a1614; }
.tbm-venue-sub { font-size: 11px; color: #76706a; margin-top: 1px; }

.tbm-section { margin-bottom: 12px; }
.tbm-section-lbl { font-size: 10px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: #a39d96; margin-bottom: 6px; }
.tbm-avail { display: flex; gap: 6px; }
.tbm-avail-btn {
    flex: 1; display: flex; align-items: center; justify-content: center; gap: 5px;
    border: 1px solid #e8e4db; background: #fff; border-radius: 7px;
    padding: 6px 4px; font-size: 11px; color: #1a1614; cursor: pointer;
}
.tbm-avail-btn:hover { background: #fbfaf6; }
.tbm-avail-on { background: #1a1614; border-color: #1a1614; color: #fff; }
.tbm-avail-dot { width: 6px; height: 6px; border-radius: 50%; flex-shrink: 0; }

.tbm-list { padding: 4px 0; margin-bottom: 4px; border-top: 1px solid #f3f0ea; }
.tbm-item {
    width: 100%; display: flex; align-items: center; justify-content: space-between;
    background: none; border: none; padding: 8px 6px; font-size: 13px; color: #1a1614;
    cursor: pointer; border-radius: 6px; text-align: left;
}
.tbm-item:hover { background: #fbfaf6; }
.tbm-count { font-size: 11px; color: #76706a; background: #f3f0e8; padding: 1px 7px; border-radius: 20px; font-weight: 600; }
.tbm-count-warn { background: #fef3c7; color: #92400e; }
.tbm-kbd { font-family: ui-monospace, monospace; font-size: 10.5px; background: #f3f0e8; color: #76706a; padding: 1px 6px; border-radius: 4px; }

.tbm-signout {
    width: 100%; display: flex; align-items: center; justify-content: space-between;
    background: none; border: none; border-top: 1px solid #f3f0ea; margin-top: 4px;
    padding: 10px 6px 2px; font-size: 13px; font-weight: 600; color: #1a1614; cursor: pointer; text-align: left;
}
.tbm-signout:hover { color: #0f766e; }

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

</style>
