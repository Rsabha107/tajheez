<script setup>
import { ref } from "vue";
import { Head, Link, useForm } from "@inertiajs/vue3";
import AuthShell from "@/Components/AuthShell.vue";
import PasswordInput from "@/Components/PasswordInput.vue";

const validated = ref(false);

const props = defineProps({
  canResetPassword: {
    type: Boolean,
  },
  status: {
    type: String,
  },
  remember: {
    type: Boolean,
  },
});

const canReset = ref(true);
const remember = ref(false);

const form = useForm({
  email: "",
  password: "",
  remember: props.remember || false,
});

function submit(e) {
  const htmlForm = e.currentTarget;

  if (!htmlForm.checkValidity()) {
    e.preventDefault();
    e.stopPropagation();
    validated.value = true;
    return;
  }

  validated.value = true;

  form.post(route("login"), {
    onFinish: () => form.reset("password"),
  });
}
</script>

<template>
  <Head title="Sign in" />
  <AuthShell title="Welcome back" subtitle="Sign in to your Tajheez account">
    <form
      @submit.prevent="submit"
      class="needs-validation"
      :class="{ 'was-validated': validated }"
      novalidate
    >
      <div v-if="status" class="alert alert-success" role="alert">
        {{ status }}
      </div>

      <div v-if="$page.props.flash?.error" class="alert alert-danger" role="alert">
        {{ $page.props.flash.error }}
      </div>

      <div v-if="form.errors.email" class="alert alert-danger" role="alert">
        {{ form.errors.email }}
      </div>

      <div class="mb-3">
        <label class="form-label" for="email">Email</label>
        <input
          class="form-control"
          id="email"
          placeholder="Enter email"
          type="email"
          v-model="form.email"
          required
          :class="{ 'is-invalid': form.errors.email }"
        />
        <div class="invalid-feedback" v-if="form.errors.email"></div>
        <div class="invalid-feedback" v-else>Please enter a valid email.</div>
      </div>

      <PasswordInput
        v-model="form.password"
        :error="form.errors.password"
        label="Password"
        placeholder="Enter password"
        id="password"
      />

      <div class="form-check" v-if="remember">
        <input
          class="form-check-input"
          id="remember-check"
          type="checkbox"
          v-model="form.remember"
        />
        <label class="form-check-label" for="remember-check">Remember me</label>
      </div>

      <div class="mt-3 d-grid">
        <button type="submit" class="btn btn-primary" :disabled="form.processing">
          {{ form.processing ? "Signing in..." : "Login" }}
        </button>
      </div>

      <div class="as-divider"><span>or</span></div>

      <a href="/auth/microsoft/redirect" class="as-sso-btn" title="Sign in with Microsoft">
        <i class="mdi mdi-microsoft-windows"></i> Sign in with Microsoft
      </a>

      <div class="mt-3 text-center">
        <Link v-if="canReset" class="as-muted-link" :href="route('myforgotpassword')">
          Forgot your password?
        </Link>
      </div>
    </form>

    <template #footer>
      <p class="mb-0">
        Don't have an account?
        <Link :href="route('myregister')">Sign up</Link>
      </p>
    </template>
  </AuthShell>
</template>

<style scoped>
.as-divider {
  display: flex; align-items: center; gap: 10px;
  margin: 18px 0 14px;
  font-size: 11.5px; color: #a39d96; text-transform: uppercase; letter-spacing: .05em;
}
.as-divider::before, .as-divider::after {
  content: ''; flex: 1; height: 1px; background: #e8e4db;
}

.as-sso-btn {
  display: flex; align-items: center; justify-content: center; gap: 8px;
  width: 100%; padding: 9px 14px;
  border: 1px solid #e8e4db; border-radius: 8px;
  background: #fff; color: #1a1614;
  font-size: 13.5px; font-weight: 600; text-decoration: none;
  transition: background .15s;
}
.as-sso-btn:hover { background: #fbfaf6; color: #1a1614; }

.as-muted-link { font-size: 12.5px; color: #76706a; text-decoration: none; }
.as-muted-link:hover { color: #0f766e; text-decoration: underline; }
</style>
