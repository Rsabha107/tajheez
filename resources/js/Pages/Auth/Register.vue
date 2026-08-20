<script setup>
import { ref } from "vue";
import { Head, Link, useForm } from "@inertiajs/vue3";
import AuthShell from "@/Components/AuthShell.vue";
import PasswordInput from "@/Components/PasswordInput.vue";

const validated = ref(false);

const form = useForm({
  name: "",
  email: "",
  password: "",
  password_confirmation: "",
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

  form.post(route("register"), {
    onFinish: () => form.reset("password", "password_confirmation"),
  });
}
</script>

<template>
  <Head title="Register" />
  <AuthShell title="Create your account" subtitle="Get set up on Tajheez in a minute." wide>
    <form
      @submit.prevent="submit"
      class="needs-validation"
      :class="{ 'was-validated': validated }"
      novalidate
    >
      <div class="mb-3">
        <label class="form-label" for="useremail">Email</label>
        <input
          class="form-control"
          id="useremail"
          placeholder="Enter email"
          type="email"
          v-model="form.email"
          required
          :class="{ 'is-invalid': form.errors.email }"
        />
        <div class="invalid-feedback" v-if="form.errors.email">{{ form.errors.email }}</div>
        <div class="invalid-feedback" v-else>Please enter a valid email.</div>
      </div>

      <div class="mb-3">
        <label class="form-label" for="username">Username</label>
        <input
          class="form-control"
          id="username"
          placeholder="Enter username"
          type="text"
          v-model="form.name"
          required
          :class="{ 'is-invalid': form.errors.name }"
        />
        <div class="invalid-feedback" v-if="form.errors.name">{{ form.errors.name }}</div>
        <div class="invalid-feedback" v-else>Please enter a username.</div>
      </div>

      <PasswordInput
        v-model="form.password"
        :error="form.errors.password"
        label="Password"
        placeholder="Enter password"
        id="userpassword"
      />

      <PasswordInput
        v-model="form.password_confirmation"
        :error="form.errors.password_confirmation"
        label="Confirm Password"
        placeholder="Confirm password"
        id="userpassword_confirmation"
      />

      <div class="mt-4 d-grid">
        <button class="btn btn-primary" type="submit" :disabled="form.processing">
          {{ form.processing ? "Registering..." : "Register" }}
        </button>
      </div>

      <p class="as-terms">
        By registering you agree to the
        <a href="#">Terms of Use</a>
      </p>
    </form>

    <template #footer>
      <p class="mb-0">
        Already have an account?
        <Link :href="route('mylogin')">Login</Link>
      </p>
    </template>
  </AuthShell>
</template>

<style scoped>
.as-terms {
  margin-top: 16px; margin-bottom: 0;
  font-size: 12px; color: #76706a; text-align: center;
}
.as-terms a { color: #0f766e; text-decoration: none; }
.as-terms a:hover { text-decoration: underline; }
</style>
