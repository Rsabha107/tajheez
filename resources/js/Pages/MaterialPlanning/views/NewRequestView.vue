<script setup>
import { ref, computed, onMounted } from 'vue';

const props = defineProps({
    domains:    Array,
    venues:     Array,
    catalog:    Array,
    areas:      { type: Array, default: () => [] },
    spaces:     { type: Array, default: () => [] },
    event:      Object,
    people:     Array,
    prefillSku: { type: String, default: null },
});

// ── Static data ───────────────────────────────────────────────────────────────
const siteTypes = ['Mixed Zone','Media Centre','VVIP Lounge','Press Tribune','Volunteer Hub','Anti-Doping','Officials Room','Operations Centre','Broadcast Compound','Catering Tent','Medical Clinic','Accreditation'];

// ── Form state ────────────────────────────────────────────────────────────────
const form = ref({
    title:       'Mixed Zone build-out — Media tier 1',
    eventCode:   'FWC26',
    venue:       'USA-MET',
    siteType:    'Mixed Zone',
    siteCode:    'MET-MZN-N',
    siteName:    'Mixed Zone — North',
    area:        'MEDIA',
    space:       '3.3.0',
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

const routingCard = ref(null);
function scrollToRouting() {
    routingCard.value?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

onMounted(() => {
    if (!props.prefillSku) return;
    const item = props.catalog.find(c => c.sku === props.prefillSku);
    if (!item) return;
    formLines.value = [{ id: nextLineId++, domain: item.domain, group: item.group, sub: item.sub, sku: item.sku, qty: 1, comment: '' }];
});

// ── Helpers ───────────────────────────────────────────────────────────────────
function fmtMoney(n) { return '$' + Number(n).toLocaleString('en-US'); }

const avatarColors = ['#7c2d12','#0f766e','#b45309','#1d4ed8','#6b21a8','#155e75','#854d0e'];
function avatarColor(initials) {
    const h = (initials.charCodeAt(0) + (initials.charCodeAt(1) || 0)) % avatarColors.length;
    return avatarColors[h];
}
function personOf(ini) { return (props.people || []).find(p => p.initials === ini) || props.people?.[0] || { name: ini }; }

const spacesForArea = computed(() => props.spaces.filter(s => s.area === form.value.area));

function onAreaChange() {
    if (!spacesForArea.value.some(s => s.code === form.value.space)) {
        form.value.space = '';
    }
}
function onSpaceChange() {
    const sp = props.spaces.find(s => s.code === form.value.space);
    if (sp) {
        form.value.lsName = sp.name;
        form.value.lsCode = sp.code;
    }
}

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
function isLowStock(line) {
    const it = lineItem(line);
    return it && line.qty > it.stock * 0.6;
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
                <button class="mp-btn" @click="scrollToRouting">Preview routing</button>
                <button class="mp-btn mp-btn-primary">Submit for approval</button>
            </div>
        </div>

        <!-- Site context -->
        <div class="mp-card">
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

        <!-- Logical space -->
        <div class="mp-card">
            <div class="mp-card-head">
                <h3 class="mp-card-title">Logical space</h3>
                <div class="mp-card-sub">Position within the venue's logical-space taxonomy.</div>
            </div>
            <div class="mp-form-grid">
                <div class="mp-field">
                    <label>Area</label>
                    <select v-model="form.area" @change="onAreaChange">
                        <option value="">— Area —</option>
                        <option v-for="a in areas" :key="a.id" :value="a.id">{{ a.label }}</option>
                    </select>
                </div>
                <div class="mp-field">
                    <label>Space</label>
                    <select v-model="form.space" @change="onSpaceChange">
                        <option value="">— Space —</option>
                        <option v-for="s in spacesForArea" :key="s.id" :value="s.code">{{ s.name }}</option>
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
                <div class="mp-field">
                    <label>Reference layout</label>
                    <div class="mp-upload">
                        <span class="mp-upload-ico">↑</span>
                        <div>
                            <div class="mp-upload-name">layout-mixedzone-v3.1.dwg</div>
                            <div class="mp-upload-meta mono">1.4 MB · uploaded Apr 22</div>
                        </div>
                        <button class="mp-upload-x">Replace</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Items -->
        <div class="mp-card">
            <div class="mp-card-head">
                <h3 class="mp-card-title">Items</h3>
                <div class="mp-card-sub">{{ formLines.length }} lines · estimated <b class="mono">{{ fmtMoney(formTotal) }}</b></div>
            </div>
            <div class="mp-ir-head">
                <div>Domain</div><div>Group</div><div>Sub-group</div><div>Item</div>
                <div class="ta-r">Qty</div><div class="ta-r">Line total</div><div>Comment</div><div></div>
            </div>
            <div class="mp-ir-body">
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
                    <div class="mp-ir-item">
                        <select :value="l.sku" @change="updateLine(l.id,'sku',$event.target.value)">
                            <option value="">— Select item —</option>
                            <option v-for="it in catalogItemsFor(l.domain,l.group,l.sub)" :key="it.sku" :value="it.sku">{{ it.name }}</option>
                        </select>
                        <div v-if="lineItem(l)" class="mp-ir-sku">
                            <span class="mono">{{ lineItem(l).sku }}</span>
                            <span>·</span>
                            <span>{{ lineItem(l).unit }}</span>
                            <span>·</span>
                            <span class="mono">{{ fmtMoney(lineItem(l).rate) }}</span>
                            <span>·</span>
                            <span :class="isLowStock(l) ? 'mp-ir-stock-low' : 'mp-ir-stock-ok'">
                                {{ isLowStock(l) ? 'tight stock' : 'in stock' }} {{ lineItem(l).stock }}
                            </span>
                        </div>
                    </div>
                    <input type="number" :value="l.qty" @input="updateLine(l.id,'qty',+$event.target.value)" min="0"/>
                    <div class="mono ta-r mp-ir-total">{{ lineItem(l) ? fmtMoney(lineItem(l).rate * l.qty) : '—' }}</div>
                    <input :value="l.comment" @input="updateLine(l.id,'comment',$event.target.value)" placeholder="Comment (location, spec, deadline…)"/>
                    <button class="mp-ir-x" title="Remove line" @click="removeLine(l.id)">×</button>
                </div>
            </div>
            <div class="mp-ir-foot">
                <button class="mp-btn" @click="addLine">+ Add item</button>
                <button class="mp-ir-foot-btn">Import from CSV</button>
                <button class="mp-ir-foot-btn">Copy from previous request</button>
                <div class="mp-ir-foot-total">
                    <span class="mp-ir-foot-lbl">Estimated total</span>
                    <span class="mono mp-ir-foot-val">{{ fmtMoney(formTotal) }}</span>
                </div>
            </div>
        </div>

        <!-- Routing -->
        <div class="mp-card" ref="routingCard">
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
                <div class="mp-field mp-span-2">
                    <label>Attach reference docs</label>
                    <div class="mp-upload mp-upload-empty">
                        <span class="mp-upload-ico">+</span>
                        <div class="mp-upload-name">Drop files or browse — site plans, vendor quotes, MoM</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="mp-page-foot">
            <button class="mp-btn">Save draft</button>
            <button class="mp-btn mp-btn-primary">Submit for approval</button>
        </div>
    </div>
</template>

<style scoped>
.mp-page { max-width: 1320px; margin: 0 auto; }
.mp-page-head {
    display: flex; justify-content: space-between; align-items: flex-end;
    gap: 24px; margin-bottom: 20px;
}
.mp-page-title { font-size: 24px; font-weight: 600; letter-spacing: -0.02em; color: #1a1614; margin: 0; }
.mp-page-sub { font-size: 13px; color: #76706a; margin: 4px 0 0; }
.mp-head-actions { display: flex; gap: 8px; align-items: center; flex-shrink: 0; flex-wrap: wrap; justify-content: flex-end; }

.mp-btn {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 7px 12px; border-radius: 6px;
    border: 1px solid #e8e4db; background: #fff;
    font-size: 12.5px; font-weight: 500; color: #1a1614; cursor: pointer;
    line-height: 1; transition: background .12s, border-color .12s;
}
.mp-btn:hover { background: #fbfaf6; border-color: #3d3833; }
.mp-btn:disabled { opacity: .4; cursor: not-allowed; }
.mp-btn-primary { background: #1a1614; border-color: #1a1614; color: #fff; }
.mp-btn-primary:hover { background: #0a0806; border-color: #0a0806; }

.mp-card {
    background: #fff; border: 1px solid #e8e4db; border-radius: 10px;
    box-shadow: 0 1px 0 rgba(20,16,12,.03), 0 1px 2px rgba(20,16,12,.04);
    padding: 18px; margin-bottom: 16px;
}
.mp-card-head { display: flex; align-items: baseline; justify-content: space-between; gap: 16px; margin-bottom: 14px; }
.mp-card-title { font-size: 14.5px; font-weight: 600; letter-spacing: -0.01em; color: #1a1614; margin: 0; }
.mp-card-sub { font-size: 12px; color: #76706a; }

.mp-form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px 18px; }
.mp-field { display: flex; flex-direction: column; gap: 5px; font-size: 13px; }
.mp-field label { font-weight: 600; color: #76706a; font-size: 11px; text-transform: uppercase; letter-spacing: .06em; }
.mp-field input, .mp-field select, .mp-field textarea {
    border: 1px solid #e8e4db; border-radius: 6px;
    padding: 8px 10px; font-size: 13px; background: #fff; color: #1a1614;
    outline: none; transition: border-color .15s;
}
.mp-field input:focus, .mp-field select:focus, .mp-field textarea:focus {
    border-color: #0f766e; box-shadow: 0 0 0 3px #0f766e1c;
}
.mp-field textarea { resize: vertical; min-height: 64px; font-family: inherit; }
.mp-span-2 { grid-column: span 2; }

.mp-upload {
    display: flex; align-items: center; gap: 10px;
    padding: 10px 12px; background: #fbfaf6;
    border: 1px dashed #e8e4db; border-radius: 6px;
}
.mp-upload-ico {
    width: 28px; height: 28px; border-radius: 6px; flex-shrink: 0;
    background: #0f766e1c; color: #0f766e;
    display: flex; align-items: center; justify-content: center; font-weight: 600;
}
.mp-upload-name { font-size: 12.5px; font-weight: 500; color: #1a1614; }
.mp-upload-meta { font-size: 11px; color: #76706a; }
.mp-upload-x { margin-left: auto; background: transparent; border: 0; color: #0f766e; font-size: 12px; cursor: pointer; }
.mp-upload-empty .mp-upload-name { color: #76706a; font-weight: 400; }

.mp-ir-head {
    display: grid; grid-template-columns: 110px 130px 140px 1.4fr 80px 110px 1fr 32px;
    gap: 8px; padding: 0 4px 8px;
    font-size: 10.5px; color: #76706a; text-transform: uppercase; letter-spacing: .06em; font-weight: 600;
    border-bottom: 1px solid #efece4; margin-bottom: 8px;
}
.mp-ir {
    display: grid; grid-template-columns: 110px 130px 140px 1.4fr 80px 110px 1fr 32px;
    gap: 8px; align-items: start; padding: 8px 4px;
    border-bottom: 1px dashed #efece4;
}
.mp-ir select, .mp-ir > input { border: 1px solid #e8e4db; border-radius: 5px; padding: 6px 8px; font-size: 12.5px; width: 100%; background: #fff; }
.mp-ir input[type="number"] { text-align: right; font-family: 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace; }
.mp-ir-item { display: flex; flex-direction: column; gap: 4px; min-width: 0; }
.mp-ir-item select { width: 100%; }
.mp-ir-sku { display: flex; gap: 6px; font-size: 11px; color: #76706a; flex-wrap: wrap; }
.mp-ir-stock-low { color: #b45309; font-weight: 500; }
.mp-ir-stock-ok { color: #166534; font-weight: 500; }
.mp-ir-total { font-size: 13px; font-weight: 600; padding-top: 6px; }
.mp-ir-x {
    width: 28px; height: 28px; border-radius: 5px;
    border: 1px solid #e8e4db; background: transparent; color: #76706a;
    cursor: pointer; font-size: 16px; padding: 0;
}
.mp-ir-x:hover { background: #fee2e2; color: #991b1b; border-color: #991b1b; }
.mp-ir-foot { display: flex; align-items: center; gap: 16px; margin-top: 12px; padding-top: 12px; border-top: 1px solid #efece4; flex-wrap: wrap; }
.mp-ir-foot-btn { background: transparent; border: 0; color: #0f766e; font-size: 12px; cursor: pointer; padding: 0; }
.mp-ir-foot-btn:hover { text-decoration: underline; }
.mp-ir-foot-total { margin-left: auto; display: flex; align-items: baseline; gap: 10px; }
.mp-ir-foot-lbl { font-size: 11px; color: #76706a; text-transform: uppercase; letter-spacing: .06em; }
.mp-ir-foot-val { font-size: 18px; font-weight: 600; letter-spacing: -.02em; color: #1a1614; }

.mp-route-prev {
    display: flex; align-items: center; gap: 10px;
    padding: 12px; background: #fbfaf6; border-radius: 8px; margin-top: 10px;
    overflow-x: auto;
}
.mp-route-node { display: flex; align-items: center; gap: 8px; flex-shrink: 0; }
.mp-route-name { font-size: 12.5px; font-weight: 600; color: #1a1614; }
.mp-route-role { font-size: 11px; color: #76706a; }
.mp-route-arrow { color: #a39d96; font-size: 14px; flex-shrink: 0; }

.mp-avatar {
    display: inline-flex; align-items: center; justify-content: center;
    width: 28px; height: 28px; border-radius: 50%;
    color: #fff; font-size: 11px; font-weight: 700; flex-shrink: 0;
}
.mp-avatar-sm { width: 22px; height: 22px; font-size: 9px; }

.mp-page-foot { display: flex; justify-content: flex-end; gap: 8px; margin-top: 4px; }

.mono { font-family: 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace; }
.ta-r { text-align: right; }
</style>
