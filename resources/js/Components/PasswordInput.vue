<script setup>
import { ref } from "vue";

const props = defineProps({
  modelValue: String,
  error: String,
  label: {
    type: String,
    default: "Password",
  },
  placeholder: {
    type: String,
    default: "Enter password",
  },
  id: {
    type: String,
    default: "password",
  },
});

const emit = defineEmits(["update:modelValue"]);

const showPassword = ref(false);

function toggle() {
  showPassword.value = !showPassword.value;
}
</script>

<template>
  <label v-if="label" class="form-label" :for="id">
    {{ label }}
  </label>
  <div class="mb-3 position-relative">
    <input
      :id="id"
      :type="showPassword ? 'text' : 'password'"
      class="form-control"
      :class="{ 'is-invalid': error }"
      :placeholder="placeholder"
      :value="modelValue"
      @input="emit('update:modelValue', $event.target.value)"
    />

    <!-- Eye icon -->
    <span class="password-toggle" @click="toggle">
      <i :class="showPassword ? 'bx bx-hide' : 'bx bx-show'"></i>
    </span>

    <!-- Error -->
    <div v-if="error" class="invalid-feedback">
      {{ error }}
    </div>
  </div>
</template>

<style scoped>
.password-toggle {
  position: absolute;
  top: 50%;
  right: 12px;
  transform: translateY(-50%);
  cursor: pointer;
  color: #76706a;
  z-index: 10;
  display: flex;
  align-items: center;
}

.password-toggle:hover {
  color: #0f766e;
}

/* prevent overlap */
.form-control {
  padding-right: 40px;
}
</style>