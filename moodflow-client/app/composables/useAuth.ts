import type { User } from '~/stores/auth'

export const useAuth = () => {
  const apiFetch = useApiFetch()
  const authStore = useAuthStore()
  const router = useRouter()

  const initCsrf = () => apiFetch('/sanctum/csrf-cookie')

  const fetchUser = async (): Promise<void> => {
    try {
      const { data } = await apiFetch<{ data: User }>('/api/users/me')
      authStore.setUser(data)
    } catch {
      authStore.clearUser()
    }
  }

  const register = async (
    name: string,
    email: string,
    password: string,
    passwordConfirmation: string,
  ): Promise<void> => {
    await initCsrf()
    const { data } = await apiFetch<{ data: User }>('/api/auth/register', {
      method: 'POST',
      body: { name, email, password, password_confirmation: passwordConfirmation },
    })
    authStore.setUser(data)
    await router.push(data.genres?.length ? '/' : '/onboarding')
  }

  const login = async (email: string, password: string): Promise<void> => {
    await initCsrf()
    const { data } = await apiFetch<{ data: User }>('/api/auth/login', {
      method: 'POST',
      body: { email, password },
    })
    authStore.setUser(data)
    await router.push(data.genres?.length ? '/' : '/onboarding')
  }

  const logout = async (): Promise<void> => {
    await apiFetch('/api/auth/logout', { method: 'POST' })
    authStore.clearUser()
    await router.push('/login')
  }

  return { login, register, logout, fetchUser }
}
