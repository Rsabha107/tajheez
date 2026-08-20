<script setup>
import { useForm } from "@inertiajs/vue3";
import { Head, Link } from "@inertiajs/vue3";
import AuthShell from "@/Components/AuthShell.vue";

const props = defineProps({
  token: String,
  email: String,
})

const form = useForm({
  token: props.token,
  email: props.email,
  password: '',
  password_confirmation: '',
}).dontRemember('password', 'password_confirmation')


const submit = () => form.post("/reset-password");
</script>

<template>
  <Head title="Reset Password" />
  <AuthShell title="Set a new password" subtitle="Choose a strong password for your account.">
    <form @submit.prevent="submit">
      <div v-if="form.errors.email" class="alert alert-danger" role="alert">
        {{ form.errors.email }}
      </div>

      <div class="mb-3">
        <label class="form-label" for="useremail">Email</label>
        <input
          v-model="form.email"
          :class="['form-control', { 'is-invalid': form.errors.email }]"
          id="useremail"
          placeholder="Enter email"
          type="email"
          required
        />
      </div>
      <div class="mb-3">
        <label class="form-label" for="userpassword">Password</label>
        <input
          v-model="form.password"
          :class="['form-control', { 'is-invalid': form.errors.password }]"
          id="userpassword"
          placeholder="Enter password"
          type="password"
          required
        />
        <div v-if="form.errors.password" class="invalid-feedback">{{ form.errors.password }}</div>
      </div>
      <div class="mb-3">
        <label class="form-label" for="userpassword_confirmation">Confirm Password</label>
        <input
          v-model="form.password_confirmation"
          :class="['form-control', { 'is-invalid': form.errors.password_confirmation }]"
          id="userpassword_confirmation"
          placeholder="Enter password confirmation"
          type="password"
          required
        />
        <div v-if="form.errors.password_confirmation" class="invalid-feedback">{{ form.errors.password_confirmation }}</div>
      </div>
      <div class="d-grid">
        <button class="btn btn-primary" type="submit" :disabled="form.processing">
          <span
            v-if="form.processing"
            class="spinner-border spinner-border-sm me-1"
            role="status"
            aria-hidden="true"
          ></span>
          {{ form.processing ? 'Resetting...' : 'Reset Password' }}
        </button>
      </div>
    </form>

    <template #footer>
      <p class="mb-0">
        Remember it?
        <Link :href="route('mylogin')">Sign in here</Link>
      </p>
    </template>
  </AuthShell>
</template>
