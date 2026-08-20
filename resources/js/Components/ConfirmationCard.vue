<script setup>
defineProps({
  icon: {
    type: String,
    default: 'bx bx-check-circle',
  },
  iconBg: {
    type: String,
    default: 'bg-light',
  },
  iconColor: {
    type: String,
    default: 'text-primary',
  },
  title: {
    type: String,
    default: 'Success!',
  },
  message: {
    type: String,
    default: '',
  },
  buttonText: {
    type: String,
    default: 'Back to Home',
  },
  buttonHref: {
    type: String,
    default: '/',
  },
  buttonVariant: {
    type: String,
    default: 'success',
  },
})
</script>

<template>
  <div class="cc-card">
    <div class="cc-icon"><i :class="icon"></i></div>
    <h1 class="cc-title">{{ title }}</h1>
    <p v-if="message" class="cc-message">{{ message }}</p>
    <slot />
    <a :href="buttonHref" class="cc-btn">{{ buttonText }}</a>
  </div>
</template>

<style scoped>
.cc-card {
  width: 100%; max-width: 400px;
  background: #fff;
  border: 1px solid #e8e4db;
  border-radius: 14px;
  box-shadow: 0 1px 3px rgba(26,22,20,.04), 0 12px 32px rgba(26,22,20,.06);
  padding: 34px 30px;
  text-align: center;
}
.cc-icon {
  width: 56px; height: 56px; margin: 0 auto 18px;
  display: flex; align-items: center; justify-content: center;
  background: rgba(15,118,110,.12); color: #0f766e;
  border-radius: 50%; font-size: 26px;
}
.cc-title { font-size: 20px; font-weight: 700; color: #1a1614; margin: 0 0 6px; }
.cc-message { font-size: 13px; color: #76706a; margin: 0 0 18px; line-height: 1.5; }
.cc-btn {
  display: inline-flex; align-items: center; justify-content: center;
  margin-top: 18px; padding: 10px 22px;
  background: #0f766e; color: #fff;
  border-radius: 8px; font-size: 13.5px; font-weight: 600; text-decoration: none;
}
.cc-btn:hover { background: #0d9488; color: #fff; }
</style>
