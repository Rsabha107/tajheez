<script setup>
import { Head } from '@inertiajs/vue3'
import ConfirmationCard from '@/Components/ConfirmationCard.vue'

defineProps({
  icon: { type: String, default: 'bx bx-mail-send' },
  iconBg: { type: String, default: 'bg-light' },
  iconColor: { type: String, default: 'text-primary' },
  title: { type: String, default: 'Success!' },
  message: { type: String, default: '' },
  buttonText: { type: String, default: 'Back to Home' },
  buttonHref: { type: String, default: '/' },
  buttonVariant: { type: String, default: 'success' },
})
</script>

<template>
  <Head title="Confirmation" />
  <div class="cc-page">
    <ConfirmationCard
      :icon="icon"
      :icon-bg="iconBg"
      :icon-color="iconColor"
      :title="title"
      :message="message"
      :button-text="buttonText"
      :button-href="buttonHref"
      :button-variant="buttonVariant"
    />
  </div>
</template>

<style scoped>
.cc-page {
  min-height: 100vh;
  display: flex; align-items: center; justify-content: center;
  padding: 32px 16px;
  background: #f6f5f1;
}
</style>
