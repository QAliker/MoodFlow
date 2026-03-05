<script setup lang="ts">
definePageMeta({ middleware: 'auth' })

const { logout } = useAuth()
const authStore = useAuthStore()
</script>

<template>
  <div class="min-h-screen flex items-center justify-center bg-gray-50 dark:bg-gray-950 p-4">
    <UCard class="w-full max-w-sm">
      <template #header>
        <h1 class="text-xl font-semibold">Welcome, {{ authStore.user?.name }}</h1>
      </template>

      <p class="text-gray-500 text-sm">{{ authStore.user?.email }}</p>

      <template #footer>
        <UButton label="Sign out" color="neutral" variant="outline" block @click="logout" />
      </template>
    </UCard>
  </div>
</template>
