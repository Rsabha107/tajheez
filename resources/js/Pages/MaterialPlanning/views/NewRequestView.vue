<script setup>
import { ref, computed, onMounted } from 'vue';

const props = defineProps({
    domains:    Array,
    venues:     Array,
    catalog:    Array,
    event:      Object,
    people:     Array,
    prefillSku: { type: String, default: null },
});

// ── Static data ───────────────────────────────────────────────────────────────
const formSteps = [
    { label: 'Context',        sub: 'Event · venue · site' },
    { label: 'Logical space',  sub: 'Where in the venue' },
    { label: 'Items',          sub: 'Pick from catalog' },
    { label: 'Routing',        sub: 'Approval & notes' },
];
const siteTypes = ['Mixed Zone','Media Centre','VVIP Lounge','Press Tribune','Volunteer Hub','Anti-Doping','Officials Room','Operations Centre','Broadcast Compound','Catering Tent','Medical Clinic','Accreditation'];

// ── Form state ────────────────────────────────────────────────────────────────
const formStep = ref(0);
const form = ref({
    title:       'Mixed Zone build-out — Media tier 1',
    eventCode:   'FWC26',
    venue:       'USA-MET',
    siteType:    'Mixed Zone',
    siteCode:    'MET-MZN-N',
    siteName:    'Mixed Zone — North',
    lsCategory:  '_3.Media',
    lsSub:       '_3.3_Mixed_Zone',
    lsName:      'Mixed Zone',
    lsCode:      '3.3.0',
    baseRoom:    'No',
    moveIn:      '2026-06-04',
    moveOut:     '2026-07-22',
    priority:    'High',
    approver:    'Auto-route (multi-step)',
    notes:       'Footprint confirmed with overlay v3.1. Coordinate with broadcast for cable runs.',
});
const formLines = ref([
    { id: 1, domain: 'OVR', group: 'Flooring',   sub: 'Modular',     sku: 'OV-FL-0420', qty: 240, comment: 'Per layout v3.1 — North end' },
    { id: 2, domain: 'OVR', group: 'Fencing',    sub: 'Heras',       sku: 'OV-FN-2200', qty: 18,  comment: 'Crowd separation' },
    { id: 3, domain: 'LOG', group: 'Tents',      sub: 'Structures',  sku: 'LG-TN-1010', qty: 2,   comment: 'Broadcaster waiting' },
    { id: 4, domain: 'IT',  group: 'AV',         sub: 'Displays',    sku: 'IT-AV-0102', qty: 4,   comment: 'Run-of-show feed' },
]);
let nextLineId = 5;

onMounted(() => {
    if (!props.prefillSku) return;
    const item = props.catalog.find(c => c.sku === props.prefillSku);
    if (!item) return;
    formLines.value = [{ id: nextLineId++, domain: item.domain, group: item.group, sub: item.sub, sku: item.sku, qty: 1, comment: '' }];
    formStep.value = 2;
});

// ── Helpers ───────────────────────────────────────────────────────────────────
function fmtMoney(n) { return '$' + Number(n).toLocaleString('en-US'); }

const avatarColors = ['#7c2d12','#0f766e','#b45309','#1d4ed8','#6b21a8','#155e75','#854d0e'];
function avatarColor(initials) {
    const h = (initials.charCodeAt(0) + (initials.charCodeAt(1) || 0)) % avatarColors.length;
    return avatarColors[h];
}
function personOf(ini) { return (props.people || []).find(p => p.initials === ini) || props.people?.[0] || { name: ini }; }

function catalogGroupsFor(domain) {
    return [...new Set(props.catalog.filter(c => c.domain === domain).map(c => c.group))];
}
function catalogSubsFor(domain, group) {
    return [...new Set(props.catalog.filter(c => c.domain === domain && c.group === group).map(c => c.sub))];
}
function catalogItemsFor(domain, group, sub) {
    return props.catalog.filter(c => c.domain === domain && c.group === group && c.sub === sub);
}
function lineItem(line) {
    return props.catalog.find(c => c.sku === line.sku);
}
function addLine() {
    formLines.value.push({ id: nextLineId++, domain: 'IT', group: '', sub: '', sku: '', qty: 1, comment: '' });
}
function removeLine(id) {
    formLines.value = formLines.value.filter(l => l.id !== id);
}
function updateLine(id, field, val) {
    const l = formLines.value.find(l => l.id === id);
    if (!l) return;
    l[field] = val;
    if (field === 'domain') { l.group = ''; l.sub = ''; l.sku = ''; }
    if (field === 'group')  { l.sub = ''; l.sku = ''; }
    if (field === 'sub')    { l.sku = ''; }
}
const formTotal = computed(() =>
    formLines.value.reduce((s, l) => {
        const it = lineItem(l);
        return s + (it ? it.rate * l.qty : 0);
    }, 0)
);
</script>

<template>
    <div class="mp-page">
        <div class="mp-page-head">
            <div>
                <h1 class="mp-page-title">New material request</h1>
                <p class="mp-page-sub">Auto-saved · draft <span class="mono">MR-26-04190</span> · last saved 4 sec ago</p>
            </div>
            <div class="mp-head-actions">
                <button class="mp-btn">Save draft</button>
                <button class="mp-btn mp-btn-primary">Submit for approval</button>
            </div>
        </div>

        <!-- Step header -->
        <ol class="mp-steps">
            <li
                v-for="(s, i) in formSteps" :key="i"
                class="mp-step"
                :class="{ 'mp-step-on': i === formStep, 'mp-step-done': i < formStep }"
                @click="formStep = i"
            >
                <span class="mp-step-n">{{ i < formStep ? '✓' : i + 1 }}</span>
                <div>
                    <div class="mp-step-lbl">{{ s.label }}</div>
                    <div class="mp-step-sub">{{ s.sub }}</div>
                </div>
            </li>
        </ol>

        <!-- Step 0 — Context -->
        <div v-if="formStep === 0" class="mp-card">
            <div class="mp-card-head">
                <h3 class="mp-card-title">Site context</h3>
                <div class="mp-card-sub">All requests are scoped to one site within the active event.</div>
            </div>
            <div class="mp-form-grid">
                <div class="mp-field mp-span-2">
                    <label>Request title</label>
                    <input v-model="form.title"/>
                </div>
                <div class="mp-field">
                    <label>Event</label>
                    <select v-model="form.eventCode">
                        <option value="FWC26">FWC26 — FIFA World Cup 2026</option>
                    </select>
                </div>
                <div class="mp-field">
                    <label>Priority</label>
                    <select v-model="form.priority">
                        <option v-for="p in ['Low','Medium','High','Critical']" :key="p">{{ p }}</option>
                    </select>
                </div>
                <div class="mp-field">
                    <label>Venue</label>
                    <select v-model="form.venue">
                        <option v-for="v in venues" :key="v.code" :value="v.code">{{ v.code }} · {{ v.name }}</option>
                    </select>
                </div>
                <div class="mp-field">
                    <label>Site type</label>
                    <select v-model="form.siteType">
                        <option v-for="t in siteTypes" :key="t">{{ t }}</option>
                    </select>
                </div>
                <div class="mp-field">
                    <label>Site code</label>
                    <input v-model="form.siteCode" class="mono"/>
                </div>
                <div class="mp-field">
                    <label>Site name</label>
                    <input v-model="form.siteName"/>
                </div>
                <div class="mp-field">
                    <label>Move-in</label>
                    <input type="date" v-model="form.moveIn"/>
                </div>
                <div class="mp-field">
                    <label>Move-out</label>
                    <input type="date" v-model="form.moveOut"/>
                </div>
            </div>
        </div>

        <!-- Step 1 — Logical space -->
        <div v-if="formStep === 1" class="mp-card">
            <div class="mp-card-head">
                <h3 class="mp-card-title">Logical space</h3>
                <div class="mp-card-sub">Position within the venue's logical-space taxonomy.</div>
            </div>
            <div class="mp-form-grid">
                <div class="mp-field">
                    <label>Category</label>
                    <select v-model="form.lsCategory">
                        <option v-for="c in ['_1.Sport','_2.Operations','_3.Media','_4.Commercial','_5.Workforce']" :key="c">{{ c }}</option>
                    </select>
                </div>
                <div class="mp-field">
                    <label>Sub-category</label>
                    <select v-model="form.lsSub">
                        <option v-for="s in ['_3.1_Press_Tribune','_3.2_TV_Compound','_3.3_Mixed_Zone','_3.4_Photo_Position']" :key="s">{{ s }}</option>
                    </select>
                </div>
                <div class="mp-field">
                    <label>Name</label>
                    <input v-model="form.lsName"/>
                </div>
                <div class="mp-field">
                    <label>Code</label>
                    <input v-model="form.lsCode" class="mono"/>
                </div>
                <div class="mp-field">
                    <label>Basebuild room</label>
                    <select v-model="form.baseRoom">
                        <option v-for="o in ['No','Yes — shared','Yes — dedicated']" :key="o">{{ o }}</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Step 2 — Items -->
        <div v-if="formStep === 2" class="mp-card">
            <div class="mp-card-head">
                <h3 class="mp-card-title">Items</h3>
                <div class="mp-card-sub">{{ formLines.length }} lines · estimated <b class="mono">{{ fmtMoney(formTotal) }}</b></div>
            </div>
            <div class="mp-ir-head">
                <div>Domain</div><div>Group</div><div>Sub-group</div><div>Item</div>
                <div class="ta-r">Qty</div><div class="ta-r">Total</div><div>Comment</div><div></div>
            </div>
            <div v-for="l in formLines" :key="l.id" class="mp-ir">
                <select :value="l.domain" @change="updateLine(l.id,'domain',$event.target.value)">
                    <option v-for="d in domains" :key="d.id" :value="d.id">{{ d.label }}</option>
                </select>
                <select :value="l.group" @change="updateLine(l.id,'group',$event.target.value)">
                    <option value="">— Group —</option>
                    <option v-for="g in catalogGroupsFor(l.domain)" :key="g">{{ g }}</option>
                </select>
                <select :value="l.sub" @change="updateLine(l.id,'sub',$event.target.value)">
                    <option value="">— Sub-group —</option>
                    <option v-for="s in catalogSubsFor(l.domain,l.group)" :key="s">{{ s }}</option>
                </select>
                <select :value="l.sku" @change="updateLine(l.id,'sku',$event.target.value)">
                    <option value="">— Select item —</option>
                    <option v-for="it in catalogItemsFor(l.domain,l.group,l.sub)" :key="it.sku" :value="it.sku">{{ it.name }}</option>
                </select>
                <input type="number" :value="l.qty" @input="updateLine(l.id,'qty',+$event.target.value)" min="0"/>
                <div class="mono ta-r">{{ lineItem(l) ? fmtMoney(lineItem(l).rate * l.qty) : '—' }}</div>
                <input :value="l.comment" @input="updateLine(l.id,'comment',$event.target.value)" placeholder="Comment…"/>
                <button class="mp-ir-x" @click="removeLine(l.id)">×</button>
            </div>
            <div class="mp-ir-foot">
                <button class="mp-btn" @click="addLine">+ Add item</button>
                <div class="mp-ir-total">
                    <span>Estimated total</span>
                    <span class="mono">{{ fmtMoney(formTotal) }}</span>
                </div>
            </div>
        </div>

        <!-- Step 3 — Routing -->
        <div v-if="formStep === 3" class="mp-card">
            <div class="mp-card-head">
                <h3 class="mp-card-title">Routing & notes</h3>
                <div class="mp-card-sub">Approval path is determined by value, domain and venue rules.</div>
            </div>
            <div class="mp-form-grid">
                <div class="mp-field mp-span-2">
                    <label>Approval routing</label>
                    <select v-model="form.approver">
                        <option v-for="o in ['Auto-route (multi-step)','Override — single approver','Override — finance-only']" :key="o">{{ o }}</option>
                    </select>
                    <div class="mp-route-prev">
                        <template v-for="(r, i) in [
                            {who:'AR',role:'You — Venue Planner'},
                            {who:'SO',role:'L1 — Site Coordinator'},
                            {who:'MC',role:'Category Lead — Overlay'},
                            {who:'DV',role:'Finance Controller (auto, value > $50k)'}
                        ]" :key="i">
                            <div class="mp-route-node">
                                <span class="mp-avatar mp-avatar-sm" :style="{ background: avatarColor(r.who) }">{{ r.who }}</span>
                                <div>
                                    <div class="mp-route-name">{{ personOf(r.who).name }}</div>
                                    <div class="mp-route-role">{{ r.role }}</div>
                                </div>
                            </div>
                            <span v-if="i < 3" class="mp-route-arrow">→</span>
                        </template>
                    </div>
                </div>
                <div class="mp-field mp-span-2">
                    <label>Notes for approvers</label>
                    <textarea v-model="form.notes" rows="3"></textarea>
                </div>
            </div>
        </div>

        <!-- Step nav -->
        <div class="mp-step-nav">
            <button class="mp-btn" :disabled="formStep === 0" @click="formStep = Math.max(0, formStep - 1)">← Back</button>
            <div class="mp-step-progress">Step {{ formStep + 1 }} of 4</div>
            <button v-if="formStep < 3" class="mp-btn mp-btn-primary" @click="formStep++">Continue →</button>
            <button v-else class="mp-btn mp-btn-primary">Submit for approval</button>
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
.mp-btn:disabled { opacity: .45; cursor: not-allowed; }
.mp-btn-primary { background: #0f766e; border-color: #0f766e; color: #fff; }
.mp-btn-primary:hover { background: #0d9488; }

.mp-card { background: #fff; border: 1px solid #e8e4db; border-radius: 10px; padding: 16px 20px; margin-bottom: 14px; }
.mp-card-head { display: flex; align-items: baseline; gap: 10px; margin-bottom: 14px; }
.mp-card-title { font-size: 14px; font-weight: 600; color: #1a1614; margin: 0; }
.mp-card-sub { font-size: 12px; color: #76706a; flex: 1; }

.mp-steps { display: flex; gap: 0; list-style: none; margin: 0 0 18px; padding: 0; }
.mp-step {
    display: flex; align-items: center; gap: 10px;
    padding: 12px 16px; flex: 1; cursor: pointer;
    background: #fff; border: 1px solid #e8e4db;
    border-right: none; font-size: 13px;
    transition: background .15s;
}
.mp-step:first-child { border-radius: 8px 0 0 8px; }
.mp-step:last-child  { border-radius: 0 8px 8px 0; border-right: 1px solid #e8e4db; }
.mp-step:hover { background: #f6f5f1; }
.mp-step-on { background: rgba(15,118,110,.1); border-color: #0f766e; color: #0f766e; }
.mp-step-done { background: #fbfaf6; color: #76706a; }
.mp-step-n {
    width: 24px; height: 24px; border-radius: 50%;
    background: #e8e4db; color: #545a6d;
    display: flex; align-items: center; justify-content: center;
    font-size: 12px; font-weight: 700; flex-shrink: 0;
}
.mp-step-on .mp-step-n { background: #0f766e; color: #fff; }
.mp-step-done .mp-step-n { background: #ccfbf1; color: #0f766e; }
.mp-step-lbl { font-weight: 600; }
.mp-step-sub { font-size: 11px; color: #76706a; }

.mp-form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
.mp-field { display: flex; flex-direction: column; gap: 5px; font-size: 13px; }
.mp-field label { font-weight: 500; color: #344054; font-size: 12px; }
.mp-field input, .mp-field select, .mp-field textarea {
    border: 1px solid #e8e4db; border-radius: 7px;
    padding: 7px 10px; font-size: 13px; background: #fff; color: #1a1614;
    outline: none; transition: border-color .15s;
}
.mp-field input:focus, .mp-field select:focus, .mp-field textarea:focus { border-color: #0f766e; }
.mp-span-2 { grid-column: span 2; }

.mp-ir-head {
    display: grid; grid-template-columns: 120px 110px 110px 1fr 70px 90px 1fr 28px;
    gap: 6px; padding: 7px 10px;
    font-size: 11px; color: #76706a; text-transform: uppercase; letter-spacing: .05em;
    border-bottom: 1px solid #e8e4db; background: #fbfaf6;
}
.mp-ir {
    display: grid; grid-template-columns: 120px 110px 110px 1fr 70px 90px 1fr 28px;
    gap: 6px; align-items: center; padding: 8px 10px;
    border-bottom: 1px solid #f3f0ea;
}
.mp-ir select, .mp-ir input { border: 1px solid #e8e4db; border-radius: 6px; padding: 5px 7px; font-size: 12px; width: 100%; background: #fff; }
.mp-ir-x { border: none; background: none; color: #76706a; cursor: pointer; font-size: 16px; padding: 0; width: 24px; }
.mp-ir-x:hover { color: #991b1b; }
.mp-ir-foot { display: flex; align-items: center; gap: 10px; padding: 12px 10px; border-top: 1px solid #e8e4db; }
.mp-ir-total { margin-left: auto; display: flex; gap: 8px; align-items: center; font-size: 13px; color: #76706a; }
.mp-ir-total .mono { font-size: 15px; font-weight: 700; color: #1a1614; }

.mp-step-nav { display: flex; align-items: center; justify-content: space-between; margin-top: 14px; }
.mp-step-progress { font-size: 13px; color: #76706a; }

.mp-route-prev {
    display: flex; align-items: center; gap: 8px;
    padding: 12px; background: #fbfaf6; border-radius: 8px; margin-top: 10px; flex-wrap: wrap;
}
.mp-route-node { display: flex; align-items: center; gap: 8px; }
.mp-route-name { font-size: 12px; font-weight: 600; color: #1a1614; }
.mp-route-role { font-size: 10px; color: #76706a; }
.mp-route-arrow { color: #76706a; font-size: 14px; }

.mp-avatar {
    display: inline-flex; align-items: center; justify-content: center;
    width: 28px; height: 28px; border-radius: 50%;
    color: #fff; font-size: 11px; font-weight: 700; flex-shrink: 0;
}
.mp-avatar-sm { width: 22px; height: 22px; font-size: 9px; }

.mono { font-family: 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace; }
.ta-r { text-align: right; }
</style>
