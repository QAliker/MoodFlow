export default defineNuxtRouteMiddleware((to) => {
  if (import.meta.server) return

  const authStore = useAuthStore()
  if (!authStore.isAuthenticated) {
    return navigateTo('/login')
  }

  if (!authStore.user?.genres?.length && to.path !== '/onboarding') {
    return navigateTo('/onboarding')
  }
})
