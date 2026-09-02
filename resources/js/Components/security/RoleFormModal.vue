<script setup>
import { ref, watch } from "vue";
import axios from "axios";
import ProgressButton from "@/Pages/MaterialPlanning/components/ProgressButton.vue";
import FormModal from "@/Pages/MaterialPlanning/components/FormModal.vue";

const props = defineProps({
  show: { type: Boolean, default: false },
  mode: { type: String, default: "create" }, // "create" | "edit"
  roleId: { type: Number, default: null },
  roleName: { type: String, default: "" },
});

const emit = defineEmits(["close", "saved"]);

const name = ref("");
const errors = ref({});
const isSaving = ref(false);

watch(
  () => [props.show, props.roleName],
  () => {
    name.value = props.roleName ?? "";
    errors.value = {};
  }
);

function closeModal() {
  emit("close");
}

async function save() {
  errors.value = {};
  if (!name.value.trim()) {
    errors.value = { name: "Role name is required." };
    return;
  }
  isSaving.value = true;
  try {
    if (props.mode === "create") {
      await axios.post("/roles", { name: name.value });
      window.toastr?.success("Role created successfully");
    } else {
      await axios.put(`/roles/${props.roleId}`, { name: name.value });
      window.toastr?.success("Role updated successfully");
    }
    emit("saved");
    closeModal();
  } catch (err) {
    const serverErrors = err.response?.data?.errors;
    if (serverErrors) errors.value = serverErrors;
    else window.toastr?.error("An error occurred. Please try again.");
  } finally {
    isSaving.value = false;
  }
}
</script>

<template>
  <FormModal
    :show="show"
    max-width="440px"
    :title="mode === 'create' ? 'Add Role' : 'Edit Role'"
    :subtitle="mode === 'create' ? 'Register a new role.' : 'Update this role\'s name.'"
    @close="closeModal"
  >
    <template #eyebrow>
      <span>Roles</span>
    </template>

    <div class="role-form">
      <label class="form-label fw-semibold" for="role-name-input">
        Role Name
      </label>
      <input
        id="role-name-input"
        v-model="name"
        type="text"
        class="form-control"
        :class="{ 'is-invalid': errors.name }"
        placeholder="Enter role name"
        @keydown.enter="save"
      />
      <div v-if="errors.name" class="invalid-feedback">
        {{ Array.isArray(errors.name) ? errors.name[0] : errors.name }}
      </div>
    </div>

    <template #footer-actions>
      <ProgressButton
        variant="primary"
        color="#0f766e"
        hover-color="#0d9488"
        :loading="isSaving"
        text="Save"
        loading-text="Saving..."
        @click="save"
      />
    </template>
  </FormModal>
</template>

<style scoped>
.role-form { padding: 20px 0; }
</style>
