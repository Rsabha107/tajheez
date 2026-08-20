<script setup>
import { useForm } from "@inertiajs/vue3";
import { Head, Link } from "@inertiajs/vue3";
import AuthShell from "@/Components/AuthShell.vue";

const props = defineProps({ status: String });

const form = useForm({ email: "" });

const submit = () => form.post("/forgot-password");
</script>

<template>
  <Head title="Forgot Password" />
  <AuthShell title="Reset your password" subtitle="Enter your email and we'll send you reset instructions.">
    <div v-if="status" class="alert alert-success" role="alert">
      {{ status }}
    </div>

    <form @submit.prevent="submit">
      <div class="mb-3">
        <label class="form-label" for="useremail">Email</label>
        <input
          v-model="form.email"
          class="form-control"
          id="useremail"
          placeholder="Enter email"
          type="email"
          required
        />
      </div>
      <div class="d-grid">
        <button class="btn btn-primary" type="submit" :disabled="form.processing">
          <span
            v-if="form.processing"
            class="spinner-border spinner-border-sm me-1"
            role="status"
            aria-hidden="true"
          ></span>
          {{ form.processing ? 'Sending...' : 'Send Reset Link' }}
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
