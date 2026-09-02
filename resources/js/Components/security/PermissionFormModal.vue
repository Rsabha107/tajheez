<script setup>
import { ref, watch, computed } from "vue";
import { router } from "@inertiajs/vue3";
import ProgressButton from "@/Pages/MaterialPlanning/components/ProgressButton.vue";
import FormModal from "@/Pages/MaterialPlanning/components/FormModal.vue";

const props = defineProps({
  show: {
    type: Boolean,
    default: false,
  },
  mode: {
    type: String,
    default: "create", // create | edit
  },
  permission: {
    type: Object,
    default: null,
  },
});

const emit = defineEmits(["close", "saved"]);

const form = ref({
  id: null,
  name: "",
});

const errors = ref({});
const saving = ref(false);

const isEdit = computed(() => props.mode === "edit");

watch(
  () => [props.show, props.permission, props.mode],
  () => {
    errors.value = {};
    saving.value = false;

    form.value = {
      id: props.permission?.id ?? null,
      name: props.permission?.name ?? "",
    };
  },
  { immediate: true, deep: true }
);

function closeModal() {
  emit("close");
}

function submit() {
  errors.value = {};
  saving.value = true;

  const payload = {
    name: form.value.name,
  };

  if (isEdit.value) {
    router.put(route("permissions.update", form.value.id), payload, {
      preserveScroll: true,
      onSuccess: () => {
        saving.value = false;
        window.toastr.success("Permission updated successfully", "Success");
        emit("saved");
        closeModal();
      },
      onError: (err) => {
        saving.value = false;
        errors.value = err;

        window.toastr.error("Failed to update permission", "Error");
      },
    });
  } else {
    router.post(route("permissions.store"), payload, {
      preserveScroll: true,
      onSuccess: () => {
        saving.value = false;
        window.toastr.success("Permission created successfully", "Success");
        emit("saved");
        closeModal();
      },
      onError: (err) => {
        saving.value = false;
        errors.value = err;
        window.toastr.error("Failed to create permission", "Error");
      },
    });
  }
}
</script>

<template>
  <FormModal
    :show="show"
    :title="isEdit ? 'Edit Permission' : 'Add Permission'"
    :subtitle="isEdit ? 'Update this permission\'s name.' : 'Register a new permission.'"
    @close="closeModal"
  >
    <template #eyebrow>
      <span>Permissions</span>
    </template>

    <form id="permission-form" class="rp-form" @submit.prevent="submit">
      <div class="mb-3">
        <label class="form-label" for="permission-name">Name</label>
        <input
          id="permission-name"
          v-model="form.name"
          type="text"
          class="form-control"
          :class="{ 'is-invalid': errors.name }"
          placeholder="Enter permission name"
        />
        <div v-if="errors.name" class="invalid-feedback d-block">
          {{ errors.name }}
        </div>
      </div>
    </form>

    <template #footer-actions>
      <ProgressButton
        variant="primary"
        color="#0f766e"
        hover-color="#0d9488"
        type="submit"
        form="permission-form"
        :loading="saving"
        :text="isEdit ? 'Update' : 'Save'"
        :loading-text="isEdit ? 'Updating...' : 'Saving...'"
      />
    </template>
  </FormModal>
</template>

<style scoped>
.rp-form { padding: 20px 0; }
</style>
