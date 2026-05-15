<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    pageTitle:     { type: String, required: true },
    activeSection: { type: String, default: '' },
    event: {
        type: Object,
        default: () => ({ code: 'FWC26', name: 'FIFA World Cup 2026', window: 'Jun 11 – Jul 19, 2026', daysOut: 27 }),
    },
});
</script>

<template>
    <div class="shell">

        <!-- ── Sidebar ──────────────────────────────────────────────────────── -->
        <aside class="shell-sidebar">
            <div class="shell-brand">
                <div class="shell-mark">T+</div>
                <div class="shell-brandtxt">
                    <div class="shell-brandname">Tajheez<span class="shell-plus">+</span></div>
                    <div class="shell-brandsub">EMS Platform</div>
                </div>
            </div>

            <div class="shell-section">
                <div class="shell-section-title">EMS Settings</div>
                <Link :href="route('events.index')" class="shell-item" :class="{ 'shell-item-active': activeSection === 'events' }">
                    <i class="bx bx-calendar shell-ico"></i><span>Events</span>
                </Link>
                <Link :href="route('venues.index')" class="shell-item" :class="{ 'shell-item-active': activeSection === 'venues' }">
                    <i class="bx bx-map shell-ico"></i><span>Venues</span>
                </Link>
                <Link :href="route('functional-areas.index')" class="shell-item" :class="{ 'shell-item-active': activeSection === 'functional-areas' }">
                    <i class="bx bx-layer shell-ico"></i><span>Functional Areas</span>
                </Link>
                <Link :href="route('material-planning.index')" class="shell-item" :class="{ 'shell-item-active': activeSection === 'material-planning' }">
                    <i class="bx bx-package shell-ico"></i><span>Material Planning</span>
                </Link>
            </div>

            <div class="shell-section">
                <div class="shell-section-title">Security</div>
                <Link :href="route('permissions.index')" class="shell-item" :class="{ 'shell-item-active': activeSection === 'permissions' }">
                    <i class="bx bx-key shell-ico"></i><span>Permissions</span>
                </Link>
                <Link :href="route('roles.index')" class="shell-item" :class="{ 'shell-item-active': activeSection === 'roles' }">
                    <i class="bx bx-shield shell-ico"></i><span>Roles</span>
                </Link>
                <Link :href="route('roles-permissions.index')" class="shell-item" :class="{ 'shell-item-active': activeSection === 'roles-permissions' }">
                    <i class="bx bx-lock-open shell-ico"></i><span>Roles &amp; Permissions</span>
                </Link>
                <Link :href="route('users.index')" class="shell-item" :class="{ 'shell-item-active': activeSection === 'users' }">
                    <i class="bx bx-group shell-ico"></i><span>Users</span>
                </Link>
            </div>

            <div class="shell-foot">
                <div class="shell-foot-lbl">Active event</div>
                <div class="shell-foot-evt">
                    <div class="shell-foot-evtcode">{{ event.code }}</div>
                    <div>
                        <div class="shell-foot-evtname">{{ event.name }}</div>
                        <div class="shell-foot-evtwin">{{ event.window }}</div>
                    </div>
                </div>
                <div class="shell-foot-count">
                    <span>T-{{ event.daysOut }} days</span>
                    <span class="shell-foot-bar"><span style="width:86%" /></span>
                </div>
            </div>
        </aside>

        <!-- ── Right column ──────────────────────────────────────────────────── -->
        <div class="shell-right">

            <!-- Topbar -->
            <header class="shell-topbar">
                <div class="tb-crumb">
                    <span class="tb-evt">{{ event.code }}</span>
                    <span class="tb-sep">/</span>
                    <span class="tb-here">{{ pageTitle }}</span>
                </div>
                <div class="tb-search">
                    <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5" stroke-linecap="round"/></svg>
                    <input placeholder="Search… (⌘K)" />
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

            <!-- Page content -->
            <div class="shell-body">
                <slot />
            </div>
        </div>
    </div>
</template>

<style scoped>
/* ── Shell layout ─────────────────────────────────────────────────────────── */
.shell {
    display: flex;
    height: 100vh;
    overflow: hidden;
    background: #f6f5f1;
}

/* ── Sidebar ──────────────────────────────────────────────────────────────── */
.shell-sidebar {
    width: 220px;
    flex-shrink: 0;
    background: #fff;
    border-right: 1px solid #e8e4db;
    display: flex;
    flex-direction: column;
    overflow-y: auto;
    height: 100vh;
}
.shell-brand {
    display: flex; align-items: center; gap: 10px;
    padding: 18px 16px 14px;
    border-bottom: 1px solid #e8e4db;
}
.shell-mark {
    width: 36px; height: 36px;
    background: #1a1614; color: #fff;
    border-radius: 8px;
    display: flex; align-items: center; justify-content: center;
    font-size: 14px; font-weight: 700; flex-shrink: 0;
}
.shell-brandname { font-size: 14px; font-weight: 700; color: #1a1614; line-height: 1.2; }
.shell-plus { color: #0f766e; }
.shell-brandsub { font-size: 10px; color: #76706a; letter-spacing: .04em; }

.shell-section { padding: 8px 8px 4px; border-top: 1px solid #e8e4db; }
.shell-section-title {
    font-size: 10px; font-weight: 700; letter-spacing: .06em;
    text-transform: uppercase; color: #76706a;
    padding: 6px 10px 4px;
}
.shell-item {
    display: flex; align-items: center; gap: 10px;
    padding: 7px 10px; border-radius: 6px;
    font-size: 13px; color: #1a1614; text-decoration: none;
    transition: background .15s;
    margin-bottom: 1px;
}
.shell-item:hover { background: #f6f5f1; color: #1a1614; }
.shell-item-active { background: rgba(15,118,110,.12) !important; color: #0f766e !important; font-weight: 600; }
.shell-ico { font-size: 16px; opacity: .85; }

.shell-foot {
    margin-top: auto;
    padding: 14px 14px 16px;
    border-top: 1px solid #e8e4db;
    font-size: 11px; color: #76706a;
}
.shell-foot-lbl { text-transform: uppercase; letter-spacing: .06em; margin-bottom: 6px; }
.shell-foot-evt { display: flex; gap: 8px; align-items: flex-start; margin-bottom: 8px; }
.shell-foot-evtcode { font-size: 11px; font-weight: 700; color: #0f766e; font-family: monospace; padding-top: 1px; }
.shell-foot-evtname { font-weight: 600; color: #1a1614; font-size: 12px; }
.shell-foot-evtwin { font-size: 10px; color: #76706a; }
.shell-foot-count { display: flex; align-items: center; gap: 8px; }
.shell-foot-bar { flex: 1; height: 4px; background: #e8e4db; border-radius: 2px; overflow: hidden; }
.shell-foot-bar span { display: block; height: 100%; background: #0f766e; }

/* ── Right column ─────────────────────────────────────────────────────────── */
.shell-right { display: flex; flex-direction: column; flex: 1; min-width: 0; height: 100vh; }

/* ── Topbar ───────────────────────────────────────────────────────────────── */
.shell-topbar {
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

/* ── Body ─────────────────────────────────────────────────────────────────── */
.shell-body { flex: 1; overflow-y: auto; overflow-x: hidden; padding: 24px 28px; background: #f6f5f1; }
</style>
