<script setup>
import { ref, watch, computed, onMounted } from "vue";
import { useForm } from "@inertiajs/vue3";
import axios from "axios";

import UserFormFields from "@/Components/forms/UserFormFields.vue";

const statuses = ref([]);
const allRoles = ref([]);
const roleSearch = ref("");
const isLoadingRoles = ref(false);
const allFunctionalAreas = ref([]);
const faSearch = ref("");
const isLoadingFas = ref(false);

const props = defineProps({
  show: { type: Boolean, default: false },
  mode: { type: String, default: "create" },
  user: { type: Object, default: null },
});

const emit = defineEmits(["close", "saved"]);

const isEdit = computed(() => props.mode === "edit");

const form = useForm({
  id: null,
  name: "",
  email: "",
  password: "",
  status_id: "",
  role_ids: [],
  functional_area_ids: [],
});

const filteredRoles = computed(() =>
  roleSearch.value.trim()
    ? allRoles.value.filter((r) =>
        r.name.toLowerCase().includes(roleSearch.value.toLowerCase())
      )
    : allRoles.value
);

const filteredFunctionalAreas = computed(() =>
  faSearch.value.trim()
    ? allFunctionalAreas.value.filter((fa) =>
        fa.title.toLowerCase().includes(faSearch.value.toLowerCase())
      )
    : allFunctionalAreas.value
);

async function loadStatuses() {
  try {
    const res = await axios.get(route("global.statuses.get"));
    statuses.value = res.data ?? [];
  } catch (e) {
    console.error("Failed to load statuses", e);
  }
}

async function loadRoles() {
  if (allRoles.value.length) return;
  try {
    const res = await axios.get("/api/roles-permissions/all-roles");
    allRoles.value = res.data ?? [];
  } catch (e) {
    console.error("Failed to load roles", e);
  }
}

async function loadFunctionalAreas() {
  if (allFunctionalAreas.value.length) return;
  try {
    const res = await axios.get(route("functional-areas.all"));
    allFunctionalAreas.value = res.data ?? [];
  } catch (e) {
    console.error("Failed to load functional areas", e);
  }
}

onMounted(() => {
  loadStatuses();
  loadRoles();
  loadFunctionalAreas();
});

watch(
  () => [props.show, props.user, props.mode],
  async () => {
    form.clearErrors();
    form.reset();
    roleSearch.value = "";
    faSearch.value = "";

    form.id = props.user?.id ?? null;
    form.name = props.user?.name ?? "";
    form.email = props.user?.email ?? "";
    form.password = "";
    form.status_id = isEdit.value ? Number(props.user?.status_id ?? "") : "";
    form.role_ids = [];
    form.functional_area_ids = [];

    if (isEdit.value && props.user?.id) {
      isLoadingRoles.value = true;
      isLoadingFas.value = true;
      try {
        const res = await axios.get(route("users.roles", props.user.id));
        form.role_ids = res.data ?? [];
      } catch (e) {
        console.error("Failed to load user roles", e);
      } finally {
        isLoadingRoles.value = false;
      }
      try {
        const res = await axios.get(route("users.functional-areas", props.user.id));
        form.functional_area_ids = res.data ?? [];
      } catch (e) {
        console.error("Failed to load user functional areas", e);
      } finally {
        isLoadingFas.value = false;
      }
    }
  },
  { immediate: true }
);

function closeModal() {
  emit("close");
}

function submit() {
  if (isEdit.value) {
    form.put(route("users.update", form.id), {
      preserveScroll: true,
      onSuccess: () => {
        if (window.toastr) window.toastr.success("User updated successfully");
        emit("saved");
      },
    });
  } else {
    form.post(route("users.store"), {
      preserveScroll: true,
      onSuccess: () => {
        if (window.toastr) window.toastr.success("User created successfully");
        emit("saved");
      },
    });
  }
}
</script>

<template>
  <Teleport to="body">
    <div v-if="show" class="rp-modal-overlay" @click.self="closeModal">
      <div class="rp-modal-box">
        <!-- Header -->
        <div class="rp-modal-header">
          <h5 class="rp-modal-title">
            <i class="fa fa-user me-2 text-primary"></i>
            {{ isEdit ? "Edit User" : "Add User" }}
          </h5>
          <button class="btn-close" @click="closeModal"></button>
        </div>

        <!-- Body -->
        <div class="rp-modal-body">
          <form id="user-form" @submit.prevent="submit">
            <UserFormFields
              :form="form"
              :statuses="statuses"
              :is-edit="isEdit"
            />

            <!-- Roles -->
            <div class="mb-3">
              <div class="d-flex justify-content-between align-items-center mb-2">
                <label class="form-label fw-semibold mb-0">
                  Roles
                  <span class="badge-count">{{ form.role_ids.length }} selected</span>
                </label>
                <button
                  type="button"
                  class="btn btn-link btn-sm p-0 text-muted text-decoration-none"
                  @click="form.role_ids = []"
                >
                  Clear all
                </button>
              </div>

              <input
                v-model="roleSearch"
                type="text"
                class="form-control form-control-sm mb-2"
                placeholder="Search roles…"
              />

              <div class="role-list">
                <div v-if="isLoadingRoles" class="text-center py-3 role-list-empty">
                  <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                </div>
                <template v-else>
                  <label
                    v-for="role in filteredRoles"
                    :key="role.id"
                    class="role-check-item"
                    :class="{ 'is-checked': form.role_ids.includes(role.id) }"
                  >
                    <input
                      type="checkbox"
                      :value="role.id"
                      v-model="form.role_ids"
                      class="role-checkbox"
                    />
                    <span>{{ role.name }}</span>
                  </label>
                  <div
                    v-if="filteredRoles.length === 0"
                    class="text-muted small py-3 text-center role-list-empty"
                  >
                    No roles found.
                  </div>
                </template>
              </div>

              <div v-if="form.errors.role_ids" class="text-danger small mt-1">
                {{ form.errors.role_ids }}
              </div>
            </div>

            <!-- Functional Areas -->
            <div class="mb-3">
              <div class="d-flex justify-content-between align-items-center mb-2">
                <label class="form-label fw-semibold mb-0">
                  Functional Areas
                  <span class="badge-count">{{ form.functional_area_ids.length }} selected</span>
                </label>
                <button
                  type="button"
                  class="btn btn-link btn-sm p-0 text-muted text-decoration-none"
                  @click="form.functional_area_ids = []"
                >
                  Clear all
                </button>
              </div>

              <input
                v-model="faSearch"
                type="text"
                class="form-control form-control-sm mb-2"
                placeholder="Search functional areas…"
              />

              <div class="role-list">
                <div v-if="isLoadingFas" class="text-center py-3 role-list-empty">
                  <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                </div>
                <template v-else>
                  <label
                    v-for="fa in filteredFunctionalAreas"
                    :key="fa.id"
                    class="role-check-item"
                    :class="{ 'is-checked': form.functional_area_ids.includes(fa.id) }"
                  >
                    <input
                      type="checkbox"
                      :value="fa.id"
                      v-model="form.functional_area_ids"
                      class="role-checkbox"
                    />
                    <span>{{ fa.title }}</span>
                  </label>
                  <div
                    v-if="filteredFunctionalAreas.length === 0"
                    class="text-muted small py-3 text-center role-list-empty"
                  >
                    No functional areas found.
                  </div>
                </template>
              </div>

              <div v-if="form.errors.functional_area_ids" class="text-danger small mt-1">
                {{ form.errors.functional_area_ids }}
              </div>
            </div>
          </form>
        </div>

        <!-- Footer -->
        <div class="rp-modal-footer">
          <button type="button" class="btn btn-light" @click="closeModal">
            Cancel
          </button>
          <button
            type="submit"
            form="user-form"
            class="btn btn-primary"
            :disabled="form.processing"
          >
            <span
              v-if="form.processing"
              class="spinner-border spinner-border-sm me-1"
            ></span>
            {{ form.processing ? "Saving..." : isEdit ? "Update" : "Save" }}
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<style scoped>
.rp-modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.45);
  z-index: 1050;
  display: flex;
  align-items: center;
  justify-content: center;
}

.rp-modal-box {
  background: #fff;
  border-radius: 18px;
  width: 520px;
  max-width: 95vw;
  max-height: 90vh;
  display: flex;
  flex-direction: column;
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.22);
}

.rp-modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1rem 1.25rem;
  border-bottom: 1px solid #eaecf6;
  flex-shrink: 0;
}

.rp-modal-title {
  margin: 0;
  font-size: 1rem;
  font-weight: 700;
  color: #1f2937;
}

.rp-modal-body {
  padding: 1.25rem;
  overflow-y: auto;
  flex: 1 1 auto;
}

.rp-modal-footer {
  padding: 1rem 1.25rem;
  border-top: 1px solid #eaecf6;
  display: flex;
  justify-content: flex-end;
  gap: 0.5rem;
  flex-shrink: 0;
}

.badge-count {
  display: inline-block;
  background: #eef2ff;
  color: #3a5bd9;
  font-size: 11px;
  font-weight: 700;
  border-radius: 50px;
  padding: 1px 8px;
  margin-left: 6px;
}

.role-list {
  max-height: 220px;
  overflow-y: auto;
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 8px;
  padding: 2px;
}

.role-list-empty {
  grid-column: 1 / -1;
}

.role-check-item {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 12px;
  border: 1px solid #e8e4db;
  border-radius: 8px;
  background: #fff;
  cursor: pointer;
  transition: background 0.12s, border-color 0.12s, color 0.12s;
  font-size: 13px;
  color: #3d3833;
  user-select: none;
  min-width: 0;
}

.role-check-item span {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.role-check-item:hover {
  border-color: #a39d96;
}

.role-check-item.is-checked {
  background: rgba(15, 118, 110, 0.08);
  border-color: #0f766e;
  color: #0f766e;
  font-weight: 600;
}

.role-checkbox {
  width: 16px;
  height: 16px;
  border-radius: 4px;
  accent-color: #0f766e;
  flex-shrink: 0;
  cursor: pointer;
}
</style>
