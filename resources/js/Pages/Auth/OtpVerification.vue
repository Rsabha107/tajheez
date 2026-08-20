<script setup>
import { ref } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import AuthShell from '@/Components/AuthShell.vue'
import OtpInput from '@/Components/OtpInput.vue'

const props = defineProps({
  email: { type: String, required: true },
  length: { type: Number, default: 4 },
})

const otpInput = ref(null)

const form = useForm({ otp: '' })

function submit() {
  if (form.otp.length < props.length) return
  form.post(route('otp.verify'), {
    onError: () => { form.otp = ''; otpInput.value?.focus() },
  })
}

function resend() {
  useForm({}).post(route('otp.resend'), {
    onSuccess: () => { form.otp = ''; otpInput.value?.focus() },
  })
}
</script>

<template>
  <Head title="Two-Step Verification" />
  <AuthShell title="Verify your email">
    <div class="as-otp-icon"><i class="bx bxs-envelope"></i></div>
    <p class="as-otp-copy">
      Please enter the {{ length }}-digit code sent to
      <span class="as-otp-email">{{ email }}</span>
    </p>

    <div v-if="form.errors.otp" class="alert alert-danger" role="alert">
      {{ form.errors.otp }}
    </div>

    <form @submit.prevent="submit">
      <OtpInput
        ref="otpInput"
        v-model="form.otp"
        :length="length"
        :has-error="!!form.errors.otp"
        @complete="submit"
      />

      <div class="mt-4 d-grid">
        <button
          type="submit"
          class="btn btn-primary"
          :disabled="form.otp.length < length || form.processing"
        >
          <span
            v-if="form.processing"
            class="spinner-border spinner-border-sm me-1"
            role="status"
            aria-hidden="true"
          ></span>
          {{ form.processing ? 'Verifying...' : 'Confirm' }}
        </button>
      </div>
    </form>

    <template #footer>
      <p>
        Didn't receive a code?
        <button type="button" class="as-link-btn" @click="resend">Resend</button>
      </p>
      <p class="mb-0">
        <Link :href="route('mylogin')">← Back to login</Link>
      </p>
    </template>
  </AuthShell>
</template>

<style scoped>
.as-otp-icon {
  width: 48px; height: 48px; margin: 0 auto 16px;
  display: flex; align-items: center; justify-content: center;
  background: rgba(15,118,110,.12); color: #0f766e;
  border-radius: 50%; font-size: 22px;
}
.as-otp-copy { font-size: 13px; color: #76706a; text-align: center; margin: 0 0 18px; line-height: 1.5; }
.as-otp-email { font-weight: 600; color: #1a1614; }

.as-link-btn {
  background: none; border: none; padding: 0;
  color: #0f766e; font-weight: 600; font-size: 13px; cursor: pointer;
}
.as-link-btn:hover { text-decoration: underline; }
</style>
