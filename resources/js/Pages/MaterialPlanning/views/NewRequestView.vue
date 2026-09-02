<script setup>
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';
import DateField from '../components/DateField.vue';
import CopyFromRequestModal from '../components/CopyFromRequestModal.vue';

const props = defineProps({
    domains:    Array,
    venues:     Array,
    catalog:    Array,
    areas:      { type: Array, default: () => [] },
    spaces:     { type: Array, default: () => [] },
    event:      Object,
    people:     Array,
    prefillSku: { type: String, default: null },
    approvalsEnabled: { type: Boolean, default: true },
    functionalAreas: { type: Array, default: () => [] },
    editRequestCode: { type: String, default: null },
    requests:   { type: Array, default: () => [] },
    showItemValues: { type: Boolean, default: false },
});

const emit = defineEmits(['go-to', 'request-saved']);

// ── Form state ────────────────────────────────────────────────────────────────
function freshForm() {
    return {
        title: '',
        venue: '',
        functionalArea: props.functionalAreas.length === 1 ? props.functionalAreas[0].id : '',
        siteType: '',
        siteCode: '',
        siteName: '',
        area: '',
        space: '',
        lsName: '',
        lsCode: '',
        baseRoom: 'No',
        moveIn: '',
        moveOut: '',
        priority: 'Medium',
        approver: 'Auto-route (multi-step)',
        notes: '',
    };
}
const form = ref(freshForm());
const formLines = ref([]);
let nextLineId = 1;

const routingCard = ref(null);
function scrollToRouting() {
    routingCard.value?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

// ── Editing an existing draft ──────────────────────────────────────────────────
const isEditMode = ref(!!props.editRequestCode);
const loadingDraft = ref(false);

async function loadDraft(code) {
    loadingDraft.value = true;
    error.value = null;
    try {
        const { data } = await axios.get(route('mp.requests.show', code));
        createdRequestId.value = data.id;
        createdRequestCode.value = data.code;
        savedLayoutFileName.value = data.layoutFileName;

        form.value = {
            title: data.title || '',
            venue: data.venueId || '',
            functionalArea: data.functionalAreaId || '',
            siteType: data.siteType || '',
            siteCode: data.siteCode || '',
            siteName: data.site || '',
            area: data.areaId || props.areas.find(a => a.label === data.lsCategory)?.id || '',
            space: props.spaces.find(s => s.id === data.spaceId)?.code || data.lsCode || '',
            lsName: data.lsName || '',
            lsCode: data.lsCode || '',
            baseRoom: data.baseRoom || 'No',
            moveIn: data.moveIn || '',
            moveOut: data.moveOut || '',
            priority: data.priority || 'Medium',
            approver: data.approverRouting || 'Auto-route (multi-step)',
            notes: data.notes || '',
        };

        formLines.value = (data.lines || []).map(line => {
            const catalogItem = props.catalog.find(c => c.sku === line.sku);
            return {
                id: nextLineId++,
                backendId: line.id,
                domain: catalogItem?.domain || line.domain || 'IT',
                group: catalogItem?.group || '',
                sub: catalogItem?.sub || '',
                sku: line.sku,
                qty: line.qty,
                comment: line.comment || '',
            };
        });
    } catch (e) {
        error.value = 'Could not load this draft. Please try again.';
    } finally {
        loadingDraft.value = false;
    }
}

onMounted(() => {
    if (props.editRequestCode) {
        loadDraft(props.editRequestCode);
        return;
    }
    if (!props.prefillSku) return;
    const item = eventCatalog.value.find(c => c.sku === props.prefillSku);
    if (!item) return;
    formLines.value = [{ id: nextLineId++, backendId: null, domain: item.domain, group: item.group, sub: item.sub, sku: item.sku, qty: 1, comment: '' }];
});

// ── Helpers ───────────────────────────────────────────────────────────────────
function fmtMoney(n) { return '$' + Number(n).toLocaleString('en-US'); }

const avatarColors = ['#7c2d12','#0f766e','#b45309','#1d4ed8','#6b21a8','#155e75','#854d0e'];
function avatarColor(initials) {
    const h = (initials.charCodeAt(0) + (initials.charCodeAt(1) || 0)) % avatarColors.length;
    return avatarColors[h];
}
function personOf(ini) { return (props.people || []).find(p => p.initials === ini) || props.people?.[0] || { name: ini }; }

// Areas/spaces are event-scoped — only offer the active event's own areas
// and spaces (existing draft lines still resolve via the full `areas`/
// `spaces` props in loadDraft(), since a loaded draft's picks were fixed at
// creation and may belong to a different event's now-superseded rows).
const eventAreas = computed(() => props.event?.id ? props.areas.filter(a => a.eventId === props.event.id) : props.areas);
const eventSpaces = computed(() => props.event?.id ? props.spaces.filter(s => s.eventId === props.event.id) : props.spaces);

const spacesForArea = computed(() => eventSpaces.value.filter(s => s.area === form.value.area));

function onAreaChange() {
    if (!spacesForArea.value.some(s => s.code === form.value.space)) {
        form.value.space = '';
    }
}
function onSpaceChange() {
    const sp = eventSpaces.value.find(s => s.code === form.value.space);
    if (sp) {
        form.value.lsName = sp.name;
        form.value.lsCode = sp.code;
    }
}

// Catalog items are event-scoped — only offer the active event's items when
// picking a new line (existing lines still resolve via the full `catalog`
// prop in lineItem(), since a loaded draft's items were fixed at creation).
const eventCatalog = computed(() =>
    props.event?.id ? props.catalog.filter(c => c.eventId === props.event.id) : props.catalog
);

// Venue picker is scoped to whichever venues are attached to the active
// event; an event with none attached falls back to every venue.
const eventVenues = computed(() =>
    props.event?.venueIds?.length ? props.venues.filter(v => props.event.venueIds.includes(v.id)) : props.venues
);
function catalogGroupsFor(domain) {
    return [...new Set(eventCatalog.value.filter(c => c.domain === domain).map(c => c.group))];
}
function catalogSubsFor(domain, group) {
    return [...new Set(eventCatalog.value.filter(c => c.domain === domain && c.group === group).map(c => c.sub))];
}
function catalogItemsFor(domain, group, sub) {
    return eventCatalog.value.filter(c => c.domain === domain && c.group === group && c.sub === sub);
}
function lineItem(line) {
    return props.catalog.find(c => c.sku === line.sku);
}
function isLowStock(line) {
    const it = lineItem(line);
    return it && line.qty > it.stock * 0.6;
}
function addLine() {
    formLines.value.push({ id: nextLineId++, backendId: null, domain: 'IT', group: '', sub: '', sku: '', qty: 1, comment: '' });
}

// ── Copy items from a previous request ────────────────────────────────────────
const showCopyFrom = ref(false);
function onLinesCopied(lines) {
    for (const l of lines) {
        formLines.value.push({ id: nextLineId++, backendId: null, ...l });
    }
    showCopyFrom.value = false;
}

const removedLineIds = ref([]);
function removeLine(id) {
    const line = formLines.value.find(l => l.id === id);
    if (line?.backendId) removedLineIds.value.push(line.backendId);
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

// ── Reference layout file ────────────────────────────────────────────────────
const layoutFileInput = ref(null);
const layoutFile = ref(null);          // newly-picked File, not yet uploaded
const savedLayoutFileName = ref(null); // filename once persisted server-side
function pickLayoutFile() { layoutFileInput.value?.click(); }
function onLayoutFileChange(e) {
    layoutFile.value = e.target.files[0] || null;
}
function fmtBytes(n) {
    if (!n) return '';
    const kb = n / 1024;
    return kb < 1024 ? Math.round(kb) + ' KB' : (kb / 1024).toFixed(1) + ' MB';
}

// ── Persist ───────────────────────────────────────────────────────────────────
const saving = ref(false);
const error = ref(null);
const createdRequestId = ref(null);
const createdRequestCode = ref(null);
const lastSavedAt = ref(null);

const canSave = computed(() => !!props.event?.id);

// Only surfaced once the user tries to save with something missing, so the
// form doesn't open already covered in red.
const attemptedSave = ref(false);
const formFieldErrors = computed(() => ({
    venue: attemptedSave.value && !form.value.venue,
    functionalArea: attemptedSave.value && props.functionalAreas.length > 0 && !form.value.functionalArea,
    items: attemptedSave.value && !formLines.value.some(l => l.sku),
}));

async function persist(shouldSubmit) {
    if (saving.value || !canSave.value) return;
    if (!form.value.venue || (props.functionalAreas.length && !form.value.functionalArea) || !formLines.value.some(l => l.sku)) {
        attemptedSave.value = true;
    }
    if (!form.value.venue) { error.value = 'Select a venue.'; return; }
    if (props.functionalAreas.length && !form.value.functionalArea) { error.value = 'Select a functional area.'; return; }
    if (!formLines.value.some(l => l.sku)) { error.value = 'Add at least one item.'; return; }

    attemptedSave.value = false;
    saving.value = true;
    error.value = null;
    try {
        const areaLabel = props.areas.find(a => a.id === form.value.area)?.label ?? null;
        const spaceId = props.spaces.find(s => s.code === form.value.space)?.id ?? null;

        const payload = new FormData();
        payload.append('title', form.value.title.trim() || 'Untitled request');
        payload.append('event_id', props.event.id);
        payload.append('venue_id', form.value.venue);
        if (form.value.functionalArea) payload.append('functional_area_id', form.value.functionalArea);
        if (form.value.area) payload.append('area_id', form.value.area);
        if (spaceId) payload.append('space_id', spaceId);
        if (form.value.siteType) payload.append('site_type', form.value.siteType);
        if (form.value.siteCode) payload.append('site_code', form.value.siteCode);
        if (form.value.siteName) payload.append('site_name', form.value.siteName);
        if (areaLabel) payload.append('ls_category', areaLabel);
        if (form.value.lsName) payload.append('ls_name', form.value.lsName);
        if (form.value.lsCode) payload.append('ls_code', form.value.lsCode);
        payload.append('base_room', form.value.baseRoom);
        if (form.value.moveIn) payload.append('move_in', form.value.moveIn);
        if (form.value.moveOut) payload.append('move_out', form.value.moveOut);
        payload.append('priority', form.value.priority);
        payload.append('approver_routing', form.value.approver);
        if (form.value.notes) payload.append('notes', form.value.notes);
        if (layoutFile.value) payload.append('layout_file', layoutFile.value);

        let saved;
        if (!createdRequestId.value) {
            const { data } = await axios.post(route('mp.requests.store'), payload);
            saved = data;
        } else {
            payload.append('_method', 'PUT');
            const { data } = await axios.post(route('mp.requests.update', createdRequestId.value), payload);
            saved = data;
        }
        createdRequestId.value = saved.id;
        createdRequestCode.value = saved.code;
        savedLayoutFileName.value = saved.layoutFileName;
        layoutFile.value = null;

        for (const line of formLines.value) {
            if (!line.sku) continue;
            if (line.backendId) {
                await axios.put(route('mp.request-lines.update', line.backendId), {
                    qty: line.qty, comment: line.comment || null,
                });
            } else {
                const { data } = await axios.post(route('mp.request-lines.store', createdRequestId.value), {
                    sku: line.sku, qty: line.qty, comment: line.comment || null,
                });
                line.backendId = data.id;
            }
        }

        for (const lineId of removedLineIds.value) {
            await axios.delete(route('mp.request-lines.destroy', lineId));
        }
        removedLineIds.value = [];

        if (shouldSubmit) {
            const { data } = await axios.post(route('mp.requests.submit', createdRequestId.value));
            createdRequestCode.value = data.code;
        } else {
            lastSavedAt.value = new Date();
        }
        // Refresh the Requests list in the background either way — a saved draft
        // should show up there too, not just a submitted request. Only navigate
        // away on submit; "Save draft" keeps the user on this form.
        emit('request-saved', shouldSubmit);
    } catch (e) {
        error.value = e.response?.status === 403
            ? "You don't have permission to save this request."
            : (Object.values(e.response?.data?.errors ?? {})[0]?.[0] ?? 'Could not save this request. Please try again.');
    } finally {
        saving.value = false;
    }
}
</script>

<template>
    <div class="mp-page">
        <div class="mp-page-head">
            <div>
                <h1 class="mp-page-title">{{ isEditMode ? 'Edit material request' : 'New material request' }}</h1>
                <p class="mp-page-sub">
                    <template v-if="createdRequestCode">
                        Draft <span class="mono">{{ createdRequestCode }}</span><span v-if="lastSavedAt"> · saved {{ lastSavedAt.toLocaleTimeString() }}</span>
                    </template>
                    <template v-else>Not yet saved</template>
                </p>
            </div>
            <div class="mp-head-actions">
                <button class="mp-btn" :disabled="saving || loadingDraft || !canSave" @click="persist(false)">{{ saving ? 'Saving…' : 'Save draft' }}</button>
                <button class="mp-btn" @click="scrollToRouting">Preview routing</button>
                <button class="mp-btn mp-btn-primary" :disabled="saving || loadingDraft || !canSave" @click="persist(true)">{{ saving ? 'Saving…' : 'Submit for approval' }}</button>
            </div>
        </div>

        <div v-if="loadingDraft" class="mp-banner">Loading draft…</div>
        <div v-if="error" class="mp-banner mp-banner-error">{{ error }}</div>
        <div v-if="!canSave" class="mp-banner mp-banner-warn">No active event selected — pick one from the sidebar before saving a request.</div>

        <!-- Site context -->
        <div class="mp-card">
            <div class="mp-card-head">
                <h3 class="mp-card-title">Site context</h3>
                <div class="mp-card-sub">All requests are scoped to one site within the active event.</div>
            </div>
            <div class="mp-form-grid">
                <div class="mp-field">
                    <label>Venue <span class="mp-req">*</span></label>
                    <select v-model="form.venue" :class="{ 'mp-field-bad': formFieldErrors.venue }">
                        <option value="">— Venue —</option>
                        <option v-for="v in eventVenues" :key="v.id" :value="v.id">{{ v.code }} · {{ v.name }}</option>
                    </select>
                    <span v-if="formFieldErrors.venue" class="mp-field-err">Required</span>
                </div>
                <div v-if="functionalAreas.length" class="mp-field">
                    <label>Functional Area <span class="mp-req">*</span></label>
                    <select v-model="form.functionalArea" :class="{ 'mp-field-bad': formFieldErrors.functionalArea }">
                        <option value="">— Functional Area —</option>
                        <option v-for="fa in functionalAreas" :key="fa.id" :value="fa.id">{{ fa.title }}</option>
                    </select>
                    <span v-if="formFieldErrors.functionalArea" class="mp-field-err">Required</span>
                </div>
                <div class="mp-field">
                    <label>Move-in</label>
                    <DateField v-model="form.moveIn"/>
                </div>
                <div class="mp-field">
                    <label>Move-out</label>
                    <DateField v-model="form.moveOut"/>
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
                        <option v-for="a in eventAreas" :key="a.id" :value="a.id">{{ a.label }}</option>
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
                    <input ref="layoutFileInput" type="file" accept=".dwg,.dxf,.pdf,.png,.jpg,.jpeg" style="display:none" @change="onLayoutFileChange"/>
                    <div v-if="layoutFile || savedLayoutFileName" class="mp-upload">
                        <span class="mp-upload-ico">↑</span>
                        <div>
                            <div class="mp-upload-name">{{ layoutFile?.name || savedLayoutFileName }}</div>
                            <div class="mp-upload-meta mono">
                                <template v-if="layoutFile">{{ fmtBytes(layoutFile.size) }} · not yet saved</template>
                                <template v-else>uploaded</template>
                            </div>
                        </div>
                        <button type="button" class="mp-upload-x" @click="pickLayoutFile">Replace</button>
                    </div>
                    <div v-else class="mp-upload mp-upload-empty mp-upload-clickable" @click="pickLayoutFile">
                        <span class="mp-upload-ico">+</span>
                        <div class="mp-upload-name">Click to attach a DWG, DXF or PDF layout</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Items -->
        <div class="mp-card">
            <div class="mp-card-head">
                <h3 class="mp-card-title">Items <span class="mp-req">*</span></h3>
                <div class="mp-card-sub">
                    {{ formLines.length }} lines
                    <template v-if="showItemValues"> · estimated <b class="mono">{{ fmtMoney(formTotal) }}</b></template>
                </div>
            </div>
            <div v-if="formFieldErrors.items" class="mp-field-err mp-field-err-block">At least one item is required</div>
            <div class="mp-ir-head" :class="{ 'mp-ir-no-value': !showItemValues }">
                <div>Domain</div><div>Group</div><div>Sub-group</div><div>Item</div>
                <div class="ta-r">Qty</div><div v-if="showItemValues" class="ta-r">Line total</div><div>Comment</div><div></div>
            </div>
            <div class="mp-ir-body">
                <div v-for="l in formLines" :key="l.id" class="mp-ir" :class="{ 'mp-ir-no-value': !showItemValues }">
                    <select :value="l.domain" @change="updateLine(l.id,'domain',$event.target.value)">
                        <option v-for="d in domains" :key="d.id" :value="d.code">{{ d.label }}</option>
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
                            <template v-if="showItemValues">
                                <span>·</span>
                                <span class="mono">{{ fmtMoney(lineItem(l).rate) }}</span>
                            </template>
                            <span>·</span>
                            <span :class="isLowStock(l) ? 'mp-ir-stock-low' : 'mp-ir-stock-ok'">
                                {{ isLowStock(l) ? 'tight stock' : 'in stock' }} {{ lineItem(l).stock }}
                            </span>
                        </div>
                    </div>
                    <input type="number" :value="l.qty" @input="updateLine(l.id,'qty',+$event.target.value)" min="0"/>
                    <div v-if="showItemValues" class="mono ta-r mp-ir-total">{{ lineItem(l) ? fmtMoney(lineItem(l).rate * l.qty) : '—' }}</div>
                    <input :value="l.comment" @input="updateLine(l.id,'comment',$event.target.value)" placeholder="Comment (location, spec, deadline…)"/>
                    <button class="mp-ir-x" title="Remove line" @click="removeLine(l.id)">×</button>
                </div>
                <div v-if="!formLines.length" class="mp-ir-empty">No items yet — click "+ Add item" to start building this request.</div>
            </div>
            <div class="mp-ir-foot">
                <button class="mp-btn" @click="addLine">+ Add item</button>
                <button class="mp-ir-foot-btn">Import from CSV</button>
                <button class="mp-ir-foot-btn" @click="showCopyFrom = true">Copy from previous request</button>
                <div v-if="showItemValues" class="mp-ir-foot-total">
                    <span class="mp-ir-foot-lbl">Estimated total</span>
                    <span class="mono mp-ir-foot-val">{{ fmtMoney(formTotal) }}</span>
                </div>
            </div>
        </div>

        <CopyFromRequestModal
            v-if="showCopyFrom"
            :requests="requests"
            :catalog="catalog"
            :exclude-code="createdRequestCode"
            @close="showCopyFrom = false"
            @copy="onLinesCopied"
        />

        <!-- Routing -->
        <div class="mp-card" ref="routingCard">
            <div class="mp-card-head">
                <h3 class="mp-card-title">Routing & notes</h3>
                <div class="mp-card-sub">Approval path is determined by value, domain and venue rules.</div>
            </div>
            <div class="mp-form-grid">
                <div v-if="approvalsEnabled" class="mp-field mp-span-2">
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
            <button class="mp-btn" :disabled="saving || !canSave" @click="persist(false)">{{ saving ? 'Saving…' : 'Save draft' }}</button>
            <button class="mp-btn mp-btn-primary" :disabled="saving || !canSave" @click="persist(true)">{{ saving ? 'Saving…' : 'Submit for approval' }}</button>
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

.mp-banner {
    padding: 10px 14px; border-radius: 8px; font-size: 12.5px; margin-bottom: 14px;
}
.mp-banner-error { background: #fee2e2; color: #991b1b; }
.mp-banner-warn { background: #fef3c7; color: #92400e; }

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
.mp-req { color: #b91c1c; font-weight: 700; }
.mp-field input, .mp-field select, .mp-field textarea {
    border: 1px solid #e8e4db; border-radius: 6px;
    padding: 8px 10px; font-size: 13px; background: #fff; color: #1a1614;
    outline: none; transition: border-color .15s;
}
.mp-field input:focus, .mp-field select:focus, .mp-field textarea:focus {
    border-color: #0f766e; box-shadow: 0 0 0 3px #0f766e1c;
}
.mp-field input.mp-field-bad, .mp-field select.mp-field-bad, .mp-field textarea.mp-field-bad {
    border-color: #dc2626;
}
.mp-field input.mp-field-bad:focus, .mp-field select.mp-field-bad:focus, .mp-field textarea.mp-field-bad:focus {
    box-shadow: 0 0 0 3px rgba(220,38,38,.12);
}
.mp-field-err { font-size: 11px; color: #b91c1c; font-weight: 500; }
.mp-field-err-block { margin: -6px 0 10px 4px; }
.mp-field textarea { resize: vertical; min-height: 64px; font-family: inherit; }
.mp-span-2 { grid-column: span 2; }

.mp-static-field {
    border: 1px solid #e8e4db; border-radius: 6px;
    padding: 8px 10px; font-size: 13px; background: #fbfaf6; color: #1a1614;
}

.mp-upload {
    display: flex; align-items: center; gap: 10px;
    padding: 10px 12px; background: #fbfaf6;
    border: 1px dashed #e8e4db; border-radius: 6px;
}
.mp-upload-clickable { cursor: pointer; }
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
.mp-ir-head.mp-ir-no-value, .mp-ir.mp-ir-no-value { grid-template-columns: 110px 130px 140px 1.4fr 80px 1fr 32px; }
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
.mp-ir-empty { padding: 20px 4px; text-align: center; font-size: 12.5px; color: #76706a; }
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
