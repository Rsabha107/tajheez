<script setup>
const props = defineProps({
    event:    Object,
    domains:  Array,
    requests: Array,
    statuses: Object,
    venues:   Array,
    people:   Array,
});

const emit = defineEmits(['open-request', 'go-to']);

// ── Static data ──────────────────────────────────────────────────────────────
const pipelineStages = [
    { k: 'draft',     n: 14,  label: 'Draft' },
    { k: 'submitted', n: 22,  label: 'Submitted' },
    { k: 'l1',        n: 18,  label: 'L1 Review' },
    { k: 'l2',        n: 11,  label: 'Category' },
    { k: 'finance',   n: 7,   label: 'Finance' },
    { k: 'approved',  n: 46,  label: 'Approved' },
    { k: 'fulfilled', n: 62,  label: 'Fulfilled' },
];
const pipelineTotal = pipelineStages.reduce((s, x) => s + x.n, 0);

const domainRollup = [
    { id: 'IT',  open: 38, value: '$4.21M', baseline: 84, util: 78, trend: [42,44,48,52,55,58,62] },
    { id: 'LOG', open: 24, value: '$1.18M', baseline: 91, util: 88, trend: [18,20,22,21,23,24,24] },
    { id: 'PWR', open: 14, value: '$2.94M', baseline: 62, util: 71, trend: [8,10,11,12,13,14,14]  },
    { id: 'OVR', open: 31, value: '$1.62M', baseline: 88, util: 82, trend: [20,22,24,26,28,30,31] },
    { id: 'FFE', open: 9,  value: '$0.42M', baseline: 48, util: 54, trend: [3,4,5,6,7,8,9]        },
];

const criticalPath = [
    { v: 'USA-MET', site: 'Broadcast Compound',  moveIn: 'Jun 04', cutoff: 'May 14', status: 'today', pct: 100 },
    { v: 'USA-SOF', site: 'Mixed Zone — North',  moveIn: 'Jun 08', cutoff: 'May 18', status: 'days4', pct: 86  },
    { v: 'MEX-AZT', site: 'Volunteer Hub — C',   moveIn: 'Jun 09', cutoff: 'May 19', status: 'days5', pct: 80  },
    { v: 'USA-ATT', site: 'Anti-Doping — South', moveIn: 'Jun 12', cutoff: 'May 22', status: 'weeks', pct: 60  },
    { v: 'CAN-BMO', site: 'Catering — H4',       moveIn: 'Jun 14', cutoff: 'May 24', status: 'weeks', pct: 50  },
];

const recentActivity = [
    { who: 'MC', verb: 'approved',        what: 'MR-26-04188', note: 'after change order',            at: '12m' },
    { who: 'DV', verb: 'requested change',what: 'MR-26-04186', note: 'finance — reduce 500→400 kVA', at: '38m' },
    { who: 'JK', verb: 'approved',        what: 'MR-26-04185', note: 'L1 review complete',            at: '1h'  },
    { who: 'AR', verb: 'submitted',       what: 'MR-26-04179', note: 'draft promoted',                at: '2h'  },
    { who: 'LH', verb: 'reverted',        what: 'MR-26-04184', note: 'item swap rejected',            at: '3h'  },
    { who: 'SO', verb: 'commented on',    what: 'MR-26-04183', note: 'awaiting venue confirmation',   at: '4h'  },
];

// ── Helpers ───────────────────────────────────────────────────────────────────
function domainOf(id)  { return props.domains.find(d => d.id === id) || props.domains[0]; }
function venueOf(code) { return props.venues.find(v => v.code === code) || props.venues[0]; }
function personOf(ini) { return props.people.find(p => p.initials === ini) || props.people[0]; }
function fmtMoney(n)   { return '$' + Number(n).toLocaleString('en-US'); }

const avatarColors = ['#7c2d12','#0f766e','#b45309','#1d4ed8','#6b21a8','#155e75','#854d0e'];
function avatarColor(initials) {
    const h = (initials.charCodeAt(0) + (initials.charCodeAt(1) || 0)) % avatarColors.length;
    return avatarColors[h];
}

function sparkPoints(data, w, h) {
    const max = Math.max(...data), min = Math.min(...data);
    return data.map((v, i) => {
        const x = (i / (data.length - 1)) * w;
        const y = h - ((v - min) / (max - min || 1)) * h * 0.85 - h * 0.1;
        return `${x.toFixed(1)},${y.toFixed(1)}`;
    }).join(' ');
}
function sparkArea(data, w, h) {
    return `0,${h} ${sparkPoints(data, w, h)} ${w},${h}`;
}
</script>

<template>
    <div class="mp-page">
        <div class="mp-page-head">
            <div>
                <h1 class="mp-page-title">Good morning, Amal</h1>
                <p class="mp-page-sub">{{ event.name }} · T-{{ event.daysOut }} days · 6 venues · 948 sites planned</p>
            </div>
            <div class="mp-head-actions">
                <button class="mp-btn">Export brief</button>
                <button class="mp-btn mp-btn-primary" @click="emit('go-to', 'new')">+ New request</button>
            </div>
        </div>

        <!-- Stat strip -->
        <div class="mp-stats">
            <div class="mp-stat">
                <div class="mp-stat-lbl">OPEN REQUESTS</div>
                <div class="mp-stat-val">116</div>
                <div class="mp-stat-sub">↑ 12 this week · across 6 venues</div>
            </div>
            <div class="mp-stat">
                <div class="mp-stat-lbl">AWAITING YOU</div>
                <div class="mp-stat-val">7</div>
                <div class="mp-stat-sub">↑ 2 since yesterday · 3 high priority</div>
            </div>
            <div class="mp-stat">
                <div class="mp-stat-lbl">APPROVED</div>
                <div class="mp-stat-val" style="color:#0f766e">$10.36M</div>
                <div class="mp-stat-sub">↑ 6.4% · of $12.40M planned</div>
            </div>
            <div class="mp-stat">
                <div class="mp-stat-lbl">CYCLE TIME</div>
                <div class="mp-stat-val">2.4 d</div>
                <div class="mp-stat-sub">↓ 0.6 d · L1 → Finance median</div>
            </div>
            <div class="mp-stat">
                <div class="mp-stat-lbl">COVERAGE</div>
                <div class="mp-stat-val">82%</div>
                <div class="mp-stat-sub">weighted by value</div>
            </div>
        </div>

        <!-- Pipeline -->
        <div class="mp-card mp-span-2">
            <div class="mp-card-head">
                <h3 class="mp-card-title">Request pipeline</h3>
                <div class="mp-card-sub">Real-time · all venues · all domains</div>
            </div>
            <div class="mp-pipe-bar">
                <div
                    v-for="s in pipelineStages" :key="s.k"
                    class="mp-pipe-seg"
                    :style="{ flex: s.n, background: statuses[s.k]?.dot || '#ccc' }"
                    :title="`${s.label} · ${s.n}`"
                >
                    <span class="mp-pipe-n">{{ s.n }}</span>
                </div>
            </div>
            <div class="mp-pipe-legend">
                <div v-for="s in pipelineStages" :key="s.k" class="mp-pipe-lg">
                    <span class="mp-pipe-dot" :style="{ background: statuses[s.k]?.dot || '#ccc' }"/>
                    <span>{{ s.label }}</span>
                    <span class="mp-pipe-n-sm">{{ s.n }}</span>
                </div>
            </div>
            <div class="mp-pipe-total">{{ pipelineTotal }} active requests · approval median 2.4 d</div>
        </div>

        <div class="mp-grid-2">
            <!-- Domain rollup -->
            <div class="mp-card mp-span-2">
                <div class="mp-card-head">
                    <h3 class="mp-card-title">By domain</h3>
                    <a class="mp-card-link" @click="emit('go-to', 'reports')">Open reports →</a>
                </div>
                <table class="mp-rollup">
                    <thead>
                        <tr>
                            <th>Domain</th>
                            <th class="ta-r">Open</th>
                            <th class="ta-r">Approved value</th>
                            <th>Baseline coverage</th>
                            <th>7-day trend</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="r in domainRollup" :key="r.id">
                            <td>
                                <span class="mp-dtag" :style="{ background: domainOf(r.id).chip, color: domainOf(r.id).color }">
                                    <b>{{ r.id }}</b> {{ domainOf(r.id).label }}
                                </span>
                            </td>
                            <td class="ta-r mono">{{ r.open }}</td>
                            <td class="ta-r mono">{{ r.value }}</td>
                            <td>
                                <div class="mp-cov">
                                    <div class="mp-cov-bar">
                                        <span :style="{ width: r.baseline + '%', background: domainOf(r.id).color }"/>
                                    </div>
                                    <span class="mp-cov-n">{{ r.baseline }}%</span>
                                </div>
                            </td>
                            <td>
                                <svg :width="108" :height="26" class="mp-spark">
                                    <polygon :points="sparkArea(r.trend, 108, 26)" :fill="domainOf(r.id).color" opacity="0.12"/>
                                    <polyline :points="sparkPoints(r.trend, 108, 26)" fill="none" :stroke="domainOf(r.id).color" stroke-width="1.5" stroke-linejoin="round"/>
                                </svg>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Awaiting approval -->
            <div class="mp-card">
                <div class="mp-card-head">
                    <h3 class="mp-card-title">Awaiting your approval <span class="mp-tag-n">7</span></h3>
                    <a class="mp-card-link" @click="emit('go-to', 'approvals')">Queue →</a>
                </div>
                <ul class="mp-mini-list">
                    <li
                        v-for="r in requests.slice(0, 4)" :key="r.id"
                        class="mp-mini-row"
                        @click="emit('open-request', r.id)"
                    >
                        <div class="mp-mini-l">
                            <span class="mp-dtag mp-dtag-mini" :style="{ background: domainOf(r.domain).chip, color: domainOf(r.domain).color }">{{ r.domain }}</span>
                            <div>
                                <div class="mp-mini-ttl">{{ r.title }}</div>
                                <div class="mp-mini-meta">
                                    <span class="mono">{{ r.id }}</span> · <span>{{ venueOf(r.venue).name }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="mp-mini-r">
                            <span class="mono">{{ fmtMoney(r.value) }}</span>
                            <span class="mp-pill" :style="{ background: statuses[r.status]?.bg, color: statuses[r.status]?.fg }">
                                <span class="mp-pill-dot" :style="{ background: statuses[r.status]?.dot }"/>
                                {{ statuses[r.status]?.label }}
                            </span>
                        </div>
                    </li>
                </ul>
            </div>

            <!-- Recent activity -->
            <div class="mp-card">
                <div class="mp-card-head">
                    <h3 class="mp-card-title">Recent activity</h3>
                    <div class="mp-card-sub">Last 24h</div>
                </div>
                <ul class="mp-activity">
                    <li v-for="(a, i) in recentActivity" :key="i" class="mp-activity-row">
                        <span class="mp-avatar" :style="{ background: avatarColor(a.who) }">{{ a.who }}</span>
                        <div class="mp-activity-txt">
                            <div><b>{{ personOf(a.who).name }}</b> {{ a.verb }} <span class="mono">{{ a.what }}</span></div>
                            <div class="mp-activity-note">{{ a.note }}</div>
                        </div>
                        <div class="mp-activity-at">{{ a.at }}</div>
                    </li>
                </ul>
            </div>

            <!-- Critical path -->
            <div class="mp-card mp-span-2">
                <div class="mp-card-head">
                    <h3 class="mp-card-title">Critical path · approval cutoffs</h3>
                    <div class="mp-card-sub">T-21 d for IT · T-14 d for all other domains</div>
                </div>
                <div class="mp-critical">
                    <div v-for="(c, i) in criticalPath" :key="i" class="mp-crit-row">
                        <div class="mp-crit-venue">
                            <div class="mp-crit-vcode mono">{{ c.v }}</div>
                            <div class="mp-crit-site">{{ c.site }}</div>
                        </div>
                        <div class="mp-crit-track">
                            <div class="mp-crit-fill" :style="{ width: c.pct + '%' }"/>
                            <div class="mp-crit-marker" :style="{ left: c.pct + '%' }"/>
                        </div>
                        <div class="mp-crit-meta">
                            <div class="mp-crit-date">{{ c.cutoff }}</div>
                            <div :class="['mp-crit-status', 'mp-crit-' + c.status]">
                                {{ c.status === 'today' ? 'due today' : c.status === 'days4' ? '4 days' : c.status === 'days5' ? '5 days' : 'on track' }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.mp-page { max-width: 100%; }
.mp-page-head {
    display: flex; justify-content: space-between; align-items: flex-start;
    margin-bottom: 20px;
    background: #fbfaf6; border: 1px solid #e8e4db;
    border-radius: 8px; padding: 14px 18px;
}
.mp-page-title { font-size: 20px; font-weight: 700; color: #1a1614; margin: 0; }
.mp-page-sub { font-size: 13px; color: #76706a; margin: 2px 0 0; }
.mp-head-actions { display: flex; gap: 8px; align-items: center; flex-shrink: 0; }

.mp-btn {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 7px 14px; border-radius: 7px;
    border: 1px solid #e8e4db; background: #fff;
    font-size: 13px; color: #1a1614; cursor: pointer;
    transition: background .15s;
}
.mp-btn:hover { background: #f6f5f1; }
.mp-btn-primary { background: #0f766e; border-color: #0f766e; color: #fff; }
.mp-btn-primary:hover { background: #0d9488; }

.mp-stats {
    display: grid; grid-template-columns: repeat(5, 1fr);
    gap: 12px; margin-bottom: 14px;
}
.mp-stat {
    background: #fff; border: 1px solid #e8e4db;
    border-radius: 10px; padding: 14px 16px;
}
.mp-stat-lbl { font-size: 11px; color: #76706a; letter-spacing: .08em; text-transform: uppercase; margin-bottom: 4px; }
.mp-stat-val { font-size: 32px; font-weight: 700; color: #1a1614; line-height: 1.1; }
.mp-stat-sub { font-size: 11px; color: #76706a; margin-top: 4px; }

.mp-card {
    background: #fff; border: 1px solid #e8e4db;
    border-radius: 10px; padding: 16px 20px;
    margin-bottom: 14px;
}
.mp-span-2 { grid-column: span 2; }
.mp-card-head { display: flex; align-items: baseline; gap: 10px; margin-bottom: 14px; }
.mp-card-title { font-size: 14px; font-weight: 600; color: #1a1614; margin: 0; }
.mp-card-sub { font-size: 12px; color: #76706a; flex: 1; }
.mp-card-link { font-size: 12px; color: #0f766e; cursor: pointer; text-decoration: none; margin-left: auto; }

.mp-pipe-bar { display: flex; border-radius: 6px; overflow: hidden; height: 32px; margin-bottom: 10px; }
.mp-pipe-seg {
    display: flex; align-items: center; justify-content: center;
    font-size: 11px; font-weight: 600; color: #fff;
    min-width: 24px; transition: opacity .15s;
}
.mp-pipe-seg:hover { opacity: .8; }
.mp-pipe-n { font-size: 10px; }
.mp-pipe-legend { display: flex; flex-wrap: wrap; gap: 14px; margin: 8px 0 4px; }
.mp-pipe-lg { display: flex; align-items: center; gap: 5px; font-size: 12px; color: #76706a; }
.mp-pipe-dot { width: 8px; height: 8px; border-radius: 50%; display: inline-block; }
.mp-pipe-n-sm { font-size: 11px; font-weight: 600; color: #1a1614; }
.mp-pipe-total { font-size: 11px; color: #76706a; margin-top: 6px; }

.mp-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }

.mp-rollup { width: 100%; border-collapse: collapse; font-size: 13px; }
.mp-rollup th { color: #76706a; font-size: 11px; text-transform: uppercase; letter-spacing: .05em; padding: 6px 8px; text-align: left; border-bottom: 1px solid #e8e4db; }
.mp-rollup td { padding: 10px 8px; border-bottom: 1px solid #f3f0ea; vertical-align: middle; color: #1a1614; }
.mp-rollup tr:last-child td { border-bottom: none; }

.mp-cov { display: flex; align-items: center; gap: 8px; }
.mp-cov-bar { flex: 1; height: 6px; background: #f1ede3; border-radius: 3px; overflow: hidden; }
.mp-cov-bar span { display: block; height: 100%; border-radius: 3px; }
.mp-cov-n { font-size: 11px; font-family: monospace; color: #76706a; width: 34px; text-align: right; }

.mp-spark { display: block; }

.mp-dtag {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 2px 8px; border-radius: 5px;
    font-size: 12px; font-weight: 600;
}
.mp-dtag-mini { padding: 1px 5px; font-size: 11px; }

.mp-pill {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 3px 9px; border-radius: 20px; font-size: 12px; font-weight: 600;
}
.mp-pill-dot { width: 6px; height: 6px; border-radius: 50%; }

.mp-avatar {
    display: inline-flex; align-items: center; justify-content: center;
    width: 28px; height: 28px; border-radius: 50%;
    color: #fff; font-size: 11px; font-weight: 700; flex-shrink: 0;
}

.mp-mini-list { list-style: none; margin: 0; padding: 0; }
.mp-mini-row {
    display: flex; align-items: center; justify-content: space-between;
    padding: 10px 0; border-bottom: 1px solid #f3f0ea; cursor: pointer;
    transition: background .12s;
}
.mp-mini-row:last-child { border-bottom: none; }
.mp-mini-row:hover { background: #fbfaf6; margin: 0 -8px; padding: 10px 8px; border-radius: 6px; }
.mp-mini-l { display: flex; align-items: flex-start; gap: 9px; flex: 1; }
.mp-mini-ttl { font-size: 13px; color: #1a1614; font-weight: 500; }
.mp-mini-meta { font-size: 11px; color: #76706a; margin-top: 2px; }
.mp-mini-r { display: flex; flex-direction: column; align-items: flex-end; gap: 4px; flex-shrink: 0; }

.mp-activity { list-style: none; margin: 0; padding: 0; }
.mp-activity-row { display: flex; align-items: flex-start; gap: 9px; padding: 9px 0; border-bottom: 1px solid #f3f0ea; }
.mp-activity-row:last-child { border-bottom: none; }
.mp-activity-txt { flex: 1; font-size: 13px; color: #1a1614; }
.mp-activity-note { font-size: 11px; color: #76706a; margin-top: 2px; }
.mp-activity-at { font-size: 11px; color: #76706a; flex-shrink: 0; }

.mp-critical { display: flex; flex-direction: column; gap: 10px; }
.mp-crit-row { display: flex; align-items: center; gap: 14px; }
.mp-crit-venue { width: 180px; flex-shrink: 0; }
.mp-crit-vcode { font-size: 12px; color: #0f766e; }
.mp-crit-site { font-size: 12px; color: #76706a; }
.mp-crit-track { flex: 1; height: 8px; background: #e8e4db; border-radius: 4px; position: relative; overflow: visible; }
.mp-crit-fill { height: 100%; background: #0f766e; border-radius: 4px; }
.mp-crit-marker { position: absolute; top: -3px; width: 2px; height: 14px; background: #1a1614; transform: translateX(-50%); }
.mp-crit-meta { width: 80px; flex-shrink: 0; text-align: right; }
.mp-crit-date { font-size: 12px; color: #1a1614; font-weight: 600; font-family: monospace; }
.mp-crit-status { font-size: 11px; }
.mp-crit-today { color: #991b1b; font-weight: 700; }
.mp-crit-days4, .mp-crit-days5 { color: #b45309; }
.mp-crit-weeks { color: #0f766e; }

.mp-tag-n {
    display: inline-flex; align-items: center; justify-content: center;
    background: #ccfbf1; color: #0f766e;
    font-size: 11px; font-weight: 700;
    border-radius: 20px; padding: 1px 7px; margin-left: 5px;
}

.mono { font-family: 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace; }
.ta-r { text-align: right; }
</style>
