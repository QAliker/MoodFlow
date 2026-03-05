import { defineStore } from 'pinia'

export interface Genre {
  id: number
  name: string
  slug: string
  icon: string
  deezer_id: number | null
}

export interface User {
  id: number
  name: string
  email: string
  avatar: string | null
  genres?: Genre[]
}

export const useAuthStore = defineStore('auth', () => {
  const user = ref<User | null>(null)
  const isAuthenticated = computed(() => !!user.value)

  function setUser(u: User | null) {
    user.value = u
  }

  function clearUser() {
    user.value = null
  }

  return { user, isAuthenticated, setUser, clearUser }
})
