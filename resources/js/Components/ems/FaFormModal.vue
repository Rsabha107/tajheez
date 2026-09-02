<script setup>
import { ref, watch, computed } from "vue";
import axios from "axios";
import { useForm } from "@inertiajs/vue3";
import ProgressButton from "@/Pages/MaterialPlanning/components/ProgressButton.vue";
import FormModal from "@/Pages/MaterialPlanning/components/FormModal.vue";

const statuses  = ref([]);


const props = defineProps({
  show:  { type: Boolean, default: false },
  mode:  { type: String,  default: "create" },
  fa: { type: Object,  default: null },
});

const emit = defineEmits(["close", "saved"]);

const isEdit = computed(() => props.mode === "edit");

const form = useForm({
  id:          null,
  title:       "",
  active_flag: "",
});

async function loadStatuses() {
  if (statuses.value.length) return;
  try {
    const res = await axios.get(route("global.statuses.get"));
    statuses.value = res.data ?? [];
  } catch (e) {
    console.error("Failed to load statuses", e);
  }
}

watch(
  () => props.show,
  async (val) => {
    if (!val) return;
    await loadStatuses();

    form.clearErrors();
    form.reset();
    form.id          = props.fa?.id ?? null;
    form.title       = props.fa?.title ?? "";
    form.active_flag = props.fa?.active_flag ? Number(props.fa.active_flag) : "";

  }
);

function closeModal() {
  emit("close");
}

function submit() {
  const onSuccess = () => {
    window.toastr?.success(
      isEdit.value ? "Functional Area updated successfully" : "Functional Area created successfully"
    );
    emit("saved");
  };

  const options = { preserveScroll: true, forceFormData: true, onSuccess };

  if (isEdit.value) {
    form.post(route("functional-areas.update", props.fa.id), options);
  } else {
    form.post(route("functional-areas.store"), options);
  }
}
</script>

<template>
  <FormModal
    :show="show"
    :title="isEdit ? 'Edit Functional Area' : 'Add Functional Area'"
    :subtitle="isEdit ? 'Update this functional area\'s title and status.' : 'Register a new functional area.'"
    @close="closeModal"
  >
    <template #eyebrow>
      <span>Functional Areas</span>
    </template>

    <form id="functional-area-form" class="ev-form" @submit.prevent="submit">
      <div class="row mb-3">
        <div class="col-8">
          <label class="form-label fw-semibold">Functional Area Title</label>
          <input
            v-model="form.title"
            type="text"
            class="form-control"
            :class="{ 'is-invalid': form.errors.title }"
            placeholder="Enter functional area title"
          />
          <div v-if="form.errors.title" class="invalid-feedback">
            {{ form.errors.title }}
          </div>
        </div>
        <div class="col-4">
          <label class="form-label fw-semibold">Status</label>
          <select
            v-model="form.active_flag"
            class="form-select"
            :class="{ 'is-invalid': form.errors.active_flag }"
          >
            <option value="">-- Select --</option>
            <option v-for="s in statuses" :key="s.id" :value="s.id">
              {{ s.name }}
            </option>
          </select>
          <div v-if="form.errors.active_flag" class="invalid-feedback">
            {{ form.errors.active_flag }}
          </div>
        </div>
      </div>
    </form>

    <template #footer-actions>
      <ProgressButton
        variant="primary"
        color="#0f766e"
        hover-color="#0d9488"
        type="submit"
        form="functional-area-form"
        :loading="form.processing"
        :text="isEdit ? 'Update' : 'Save'"
        loading-text="Saving..."
      />
    </template>
  </FormModal>
</template>

<style scoped>
.ev-form { padding: 20px 0; }
</style>
