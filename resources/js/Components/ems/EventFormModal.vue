<script setup>
import { ref, watch, computed } from "vue";
import axios from "axios";
import { useForm } from "@inertiajs/vue3";
import ProgressButton from "@/Pages/MaterialPlanning/components/ProgressButton.vue";
import DateField from "@/Pages/MaterialPlanning/components/DateField.vue";
import FormModal from "@/Pages/MaterialPlanning/components/FormModal.vue";

import vueFilePond from "vue-filepond/dist/vue-filepond.esm.js";
import FilePondPluginImagePreview from "filepond-plugin-image-preview";
import FilePondPluginFileValidateType from "filepond-plugin-file-validate-type";

import "filepond/dist/filepond.min.css";
import "filepond-plugin-image-preview/dist/filepond-plugin-image-preview.css";

const FilePond = vueFilePond(
  FilePondPluginImagePreview,
  FilePondPluginFileValidateType
);

const statuses    = ref([]);
const allVenues   = ref([]);
const venueSearch = ref("");
const pondFiles   = ref([]);

// FilePond server: only the load handler is needed (to display the existing image)
const pondServer = {
  load: (source, load, error) => {
    fetch(source)
      .then((res) => res.blob())
      .then(load)
      .catch(error);
  },
};

const props = defineProps({
  show:  { type: Boolean, default: false },
  mode:  { type: String,  default: "create" },
  event: { type: Object,  default: null },
});

const emit = defineEmits(["close", "saved"]);

const isEdit = computed(() => props.mode === "edit");

const form = useForm({
  id:          null,
  name:        "",
  code:        "",
  start_date:  "",
  end_date:    "",
  active_flag: "",
  logo:        null,
  venue_ids:   [],
});

const filteredVenues = computed(() =>
  venueSearch.value.trim()
    ? allVenues.value.filter((v) =>
        v.title.toLowerCase().includes(venueSearch.value.toLowerCase())
      )
    : allVenues.value
);

async function loadStatuses() {
  if (statuses.value.length) return;
  try {
    const res = await axios.get(route("global.statuses.get"));
    statuses.value = res.data ?? [];
  } catch (e) {
    console.error("Failed to load statuses", e);
  }
}

async function loadVenues() {
  if (allVenues.value.length) return;
  try {
    const res = await axios.get(route("venues.all"));
    allVenues.value = res.data ?? [];
  } catch (e) {
    console.error("Failed to load venues", e);
  }
}

watch(
  () => props.show,
  async (val) => {
    if (!val) return;
    await Promise.all([loadStatuses(), loadVenues()]);

    venueSearch.value = "";
    form.clearErrors();
    form.reset();
    form.id          = props.event?.id ?? null;
    form.name        = props.event?.name ?? "";
    form.code        = props.event?.code ?? "";
    form.start_date  = props.event?.start_date ?? "";
    form.end_date    = props.event?.end_date ?? "";
    form.active_flag = props.event?.active_flag ? Number(props.event.active_flag) : "";
    form.logo        = null;
    form.venue_ids   = props.event?.venue_ids ?? [];

    if (isEdit.value && props.event?.logo_url) {
      pondFiles.value = [{ source: props.event.logo_url, options: { type: "local" } }];
    } else {
      pondFiles.value = [];
    }
  }
);

function onAddFile(_err, fileItem) {
  form.logo = fileItem.file;
}

function onRemoveFile() {
  form.logo = null;
}

function closeModal() {
  emit("close");
}

function submit() {
  const onSuccess = () => {
    window.toastr?.success(
      isEdit.value ? "Event updated successfully" : "Event created successfully"
    );
    emit("saved");
  };

  const options = { preserveScroll: true, forceFormData: true, onSuccess };

  if (isEdit.value) {
    form.post(route("events.update", props.event.id), options);
  } else {
    form.post(route("events.store"), options);
  }
}
</script>

<template>
  <FormModal
    :show="show"
    :title="isEdit ? 'Edit Event' : 'Add Event'"
    :subtitle="isEdit ? 'Update this event\'s schedule, code, status and venues.' : 'Register a new event — code and dates are optional and can be added later.'"
    @close="closeModal"
  >
    <template #eyebrow>
      <span>Events</span>
    </template>

          <form id="event-form" class="ev-form" @submit.prevent="submit">
            <!-- Logo -->
            <div class="mb-3">
              <label class="form-label fw-semibold">Event Logo</label>
              <FilePond
                name="logo"
                class="ev-filepond"
                :server="pondServer"
                label-idle='<i class="fa fa-image me-1"></i> Drop image or <span class="filepond--label-action">Browse</span>'
                accepted-file-types="image/*"
                :allow-multiple="false"
                :files="pondFiles"
                @addfile="onAddFile"
                @removefile="onRemoveFile"
              />
              <div v-if="form.errors.logo" class="text-danger small mt-1">
                {{ form.errors.logo }}
              </div>
            </div>

            <!-- Name & Code -->
            <div class="row mb-3">
              <div class="col-8">
                <label class="form-label fw-semibold">Event Name</label>
                <input
                  v-model="form.name"
                  type="text"
                  class="form-control"
                  :class="{ 'is-invalid': form.errors.name }"
                  placeholder="Enter event name"
                />
                <div v-if="form.errors.name" class="invalid-feedback">
                  {{ form.errors.name }}
                </div>
              </div>
              <div class="col-4">
                <label class="form-label fw-semibold">
                  Code <span class="text-muted fw-normal">(optional)</span>
                </label>
                <input
                  v-model="form.code"
                  type="text"
                  class="form-control"
                  :class="{ 'is-invalid': form.errors.code }"
                  placeholder="e.g. FIC25"
                />
                <div v-if="form.errors.code" class="invalid-feedback">
                  {{ form.errors.code }}
                </div>
              </div>
            </div>

            <!-- Dates -->
            <div class="row mb-3">
              <div class="col-6">
                <label class="form-label fw-semibold">Start Date</label>
                <DateField
                  v-model="form.start_date"
                  :invalid="!!form.errors.start_date"
                  :class="{ 'is-invalid': form.errors.start_date }"
                />
                <div v-if="form.errors.start_date" class="invalid-feedback">
                  {{ form.errors.start_date }}
                </div>
              </div>
              <div class="col-6">
                <label class="form-label fw-semibold">End Date</label>
                <DateField
                  v-model="form.end_date"
                  :invalid="!!form.errors.end_date"
                  :class="{ 'is-invalid': form.errors.end_date }"
                />
                <div v-if="form.errors.end_date" class="invalid-feedback">
                  {{ form.errors.end_date }}
                </div>
              </div>
            </div>

            <!-- Status -->
            <div class="mb-3">
              <label class="form-label fw-semibold">Status</label>
              <select
                v-model="form.active_flag"
                class="form-select"
                :class="{ 'is-invalid': form.errors.active_flag }"
              >
                <option value="">-- Select Status --</option>
                <option v-for="s in statuses" :key="s.id" :value="s.id">
                  {{ s.name }}
                </option>
              </select>
              <div v-if="form.errors.active_flag" class="invalid-feedback">
                {{ form.errors.active_flag }}
              </div>
            </div>

            <!-- Venues -->
            <div class="mb-3">
              <div class="d-flex justify-content-between align-items-center mb-2">
                <label class="form-label fw-semibold mb-0">
                  Venues
                  <span class="ev-badge-count">{{ form.venue_ids.length }} selected</span>
                </label>
                <ProgressButton
                  variant="bootstrap"
                  class="btn btn-link btn-sm p-0 text-muted text-decoration-none"
                  text="Clear all"
                  @click="form.venue_ids = []"
                />
              </div>
              <input
                v-model="venueSearch"
                type="text"
                class="form-control form-control-sm mb-2"
                placeholder="Search venues…"
              />
              <div class="ev-venue-list">
                <label
                  v-for="v in filteredVenues"
                  :key="v.id"
                  class="ev-venue-item"
                  :class="{ 'is-checked': form.venue_ids.includes(v.id) }"
                >
                  <input
                    type="checkbox"
                    :value="v.id"
                    v-model="form.venue_ids"
                    class="ev-venue-checkbox"
                  />
                  <span>{{ v.title }}</span>
                </label>
                <div v-if="filteredVenues.length === 0" class="text-muted small py-3 text-center">
                  No venues found.
                </div>
              </div>
              <div v-if="form.errors.venue_ids" class="text-danger small mt-1">
                {{ form.errors.venue_ids }}
              </div>
            </div>
          </form>

    <template #footer-actions>
      <ProgressButton
        variant="primary"
        color="#0f766e"
        hover-color="#0d9488"
        type="submit"
        form="event-form"
        :loading="form.processing"
        :text="isEdit ? 'Update' : 'Save'"
        loading-text="Saving..."
      />
    </template>
  </FormModal>
</template>

<style scoped>
.ev-form { padding: 20px 0; }

/* Venue picker */
.ev-badge-count {
  display: inline-block;
  background: #eef2ff;
  color: #3a5bd9;
  font-size: 11px;
  font-weight: 700;
  border-radius: 50px;
  padding: 1px 8px;
  margin-left: 6px;
}

.ev-venue-list {
  max-height: 160px;
  overflow-y: auto;
  border: 1px solid #eaecf6;
  border-radius: 12px;
  padding: 6px 4px;
}

.ev-venue-item {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 7px 12px;
  border-radius: 8px;
  cursor: pointer;
  transition: background 0.12s;
  font-size: 13px;
  color: #374151;
  user-select: none;
}

.ev-venue-item:hover { background: #f5f7ff; }

.ev-venue-item.is-checked {
  background: #eef2ff;
  color: #3a5bd9;
  font-weight: 500;
}

.ev-venue-checkbox {
  width: 16px;
  height: 16px;
  border-radius: 4px;
  border-color: #5b8df6;
  flex-shrink: 0;
  cursor: pointer;
}

/* FilePond tweaks */
:deep(.ev-filepond .filepond--root) {
  border-radius: 12px;
  font-family: inherit;
}

:deep(.ev-filepond .filepond--panel-root) {
  background: #f8f9ff;
  border: 1.5px dashed #c7d2fe;
  border-radius: 12px;
}

:deep(.ev-filepond .filepond--drop-label) {
  color: #6b7280;
  font-size: 13px;
}

:deep(.ev-filepond .filepond--label-action) {
  color: #3a5bd9;
  text-decoration: underline;
}
</style>
