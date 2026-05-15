<script setup>
const props = defineProps({
    domains:  Array,
    requests: Array,
    event:    Object,
});

// ── Static data ───────────────────────────────────────────────────────────────
const domainRollup = [
    { id: 'IT',  open: 38, value: '$4.21M', baseline: 84, util: 78, trend: [42,44,48,52,55,58,62] },
    { id: 'LOG', open: 24, value: '$1.18M', baseline: 91, util: 88, trend: [18,20,22,21,23,24,24] },
    { id: 'PWR', open: 14, value: '$2.94M', baseline: 62, util: 71, trend: [8,10,11,12,13,14,14]  },
    { id: 'OVR', open: 31, value: '$1.62M', baseline: 88, util: 82, trend: [20,22,24,26,28,30,31] },
    { id: 'FFE', open: 9,  value: '$0.42M', baseline: 48, util: 54, trend: [3,4,5,6,7,8,9]        },
];

// ── Helpers ───────────────────────────────────────────────────────────────────
function domainOf(id) { return props.domains.find(d => d.id === id) || props.domains[0]; }
</script>

<template>
    <div class="mp-page">
        <div class="mp-page-head">
            <div>
                <h1 class="mp-page-title">Reports</h1>
                <p class="mp-page-sub">Spend analytics · {{ event.name }}</p>
            </div>
            <div class="mp-head-actions">
                <button class="mp-btn">Export PDF</button>
            </div>
        </div>
        <div class="mp-grid-2">
            <div class="mp-card">
                <div class="mp-card-head"><h3 class="mp-card-title">Spend by domain</h3></div>
                <div class="mp-report-bars">
                    <div v-for="r in domainRollup" :key="r.id" class="mp-report-bar-row">
                        <div class="mp-report-bar-lbl">
                            <span class="mp-dtag" :style="{ background: domainOf(r.id).chip, color: domainOf(r.id).color }"><b>{{ r.id }}</b></span>
                            <span class="mono">{{ r.value }}</span>
                        </div>
                        <div class="mp-report-bar-track">
                            <div class="mp-report-bar-fill" :style="{ width: r.baseline + '%', background: domainOf(r.id).color }"/>
                        </div>
                    </div>
                </div>
            </div>
            <div class="mp-card">
                <div class="mp-card-head"><h3 class="mp-card-title">By domain</h3></div>
                <table class="mp-rollup">
                    <thead>
                        <tr>
                            <th>Domain</th>
                            <th class="ta-r">Open</th>
                            <th class="ta-r">Value</th>
                            <th class="ta-r">Coverage</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="r in domainRollup" :key="r.id">
                            <td><span class="mp-dtag" :style="{ background: domainOf(r.id).chip, color: domainOf(r.id).color }"><b>{{ r.id }}</b></span></td>
                            <td class="ta-r mono">{{ r.open }}</td>
                            <td class="ta-r mono">{{ r.value }}</td>
                            <td class="ta-r mono">{{ r.baseline }}%</td>
                        </tr>
                    </tbody>
                </table>
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

.mp-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }

.mp-card { background: #fff; border: 1px solid #e8e4db; border-radius: 10px; padding: 16px 20px; margin-bottom: 14px; }
.mp-card-head { display: flex; align-items: baseline; gap: 10px; margin-bottom: 14px; }
.mp-card-title { font-size: 14px; font-weight: 600; color: #1a1614; margin: 0; }

.mp-report-bars { display: flex; flex-direction: column; gap: 12px; }
.mp-report-bar-row { display: flex; flex-direction: column; }
.mp-report-bar-lbl { display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px; font-size: 12px; }
.mp-report-bar-track { height: 8px; background: #e8e4db; border-radius: 4px; overflow: hidden; }
.mp-report-bar-fill { height: 100%; border-radius: 4px; }

.mp-rollup { width: 100%; border-collapse: collapse; font-size: 13px; }
.mp-rollup th { color: #76706a; font-size: 11px; text-transform: uppercase; letter-spacing: .05em; padding: 6px 8px; text-align: left; border-bottom: 1px solid #e8e4db; }
.mp-rollup td { padding: 10px 8px; border-bottom: 1px solid #f3f0ea; vertical-align: middle; color: #1a1614; }
.mp-rollup tr:last-child td { border-bottom: none; }

.mp-dtag {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 2px 8px; border-radius: 5px;
    font-size: 12px; font-weight: 600;
}

.mono { font-family: 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace; }
.ta-r { text-align: right; }
</style>
