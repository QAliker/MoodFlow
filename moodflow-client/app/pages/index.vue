<script setup lang="ts">
definePageMeta({ middleware: 'auth' })

const authStore = useAuthStore()
</script>

<template>
  <UContainer class="py-12">
    <div class="flex flex-col gap-2">
      <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
        Welcome back, {{ authStore.user?.name }}
      </h1>
      <p class="text-gray-500 text-sm">{{ authStore.user?.email }}</p>
    </div>
  </UContainer>
</template>
