<script setup lang="ts">
const authStore = useAuthStore()
const { logout } = useAuth()
const route = useRoute()

const navLinks = [
  { to: '/', label: 'Accueil', icon: 'i-heroicons-home' },
  { to: '/mood', label: 'Analyser', icon: 'i-heroicons-face-smile' },
  { to: '/dashboard', label: 'Dashboard', icon: 'i-heroicons-chart-bar' },
]

const items = [[
  {
    label: authStore.user?.name ?? '',
    slot: 'account',
    disabled: true,
  },
], [
  {
    label: 'Sign out',
    icon: 'i-heroicons-arrow-right-on-rectangle',
    color: 'error' as const,
    onSelect: logout,
  },
]]
</script>

<template>
  <nav class="border-b border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-950 sticky top-0 z-50">
    <UContainer class="flex items-center justify-between h-16">
      <div class="flex items-center gap-6">
        <NuxtLink to="/" class="flex items-center gap-2 font-bold text-lg text-gray-900 dark:text-white shrink-0">
          <UIcon name="i-heroicons-musical-note" class="text-primary-500 w-6 h-6" />
          MoodFlow
        </NuxtLink>

        <div class="hidden sm:flex items-center gap-1">
          <NuxtLink
            v-for="link in navLinks"
            :key="link.to"
            :to="link.to"
            class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-sm font-medium transition-colors"
            :class="route.path === link.to
              ? 'bg-primary-50 dark:bg-primary-900/30 text-primary-600 dark:text-primary-400'
              : 'text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-white'"
          >
            <UIcon :name="link.icon" class="w-4 h-4" />
            {{ link.label }}
          </NuxtLink>
        </div>
      </div>

      <div class="flex items-center gap-2">
        <UColorModeButton />

        <UDropdownMenu :items="items">
          <UButton variant="ghost" color="neutral" class="gap-2 px-2">
            <UAvatar
              :src="authStore.user?.avatar ?? undefined"
              :alt="authStore.user?.name"
              size="sm"
            />
            <span class="text-sm font-medium hidden sm:block">{{ authStore.user?.name }}</span>
            <UIcon name="i-heroicons-chevron-down" class="w-4 h-4 text-gray-400" />
          </UButton>

          <template #account>
            <div class="px-1 py-1">
              <p class="text-xs font-medium text-gray-900 dark:text-white">{{ authStore.user?.name }}</p>
              <p class="text-xs text-gray-500 truncate">{{ authStore.user?.email }}</p>
            </div>
          </template>
        </UDropdownMenu>
      </div>
    </UContainer>
  </nav>
</template>
