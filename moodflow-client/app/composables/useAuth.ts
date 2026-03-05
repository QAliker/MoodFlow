import type { User } from '~/stores/auth'

export const useAuth = () => {
  const config = useRuntimeConfig()
  const authStore = useAuthStore()
  const router = useRouter()

  const getXsrfToken = (): string => {
    if (!import.meta.client) return ''
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]*)/)
    return match ? decodeURIComponent(match[1]) : ''
  }

  const apiFetch = $fetch.create({
    baseURL: config.public.apiUrl,
    credentials: 'include',
    headers: { Accept: 'application/json' },
    onRequest({ options }) {
      const token = getXsrfToken()
      if (token) {
        options.headers = { ...options.headers, 'X-XSRF-TOKEN': token }
      }
    },
  })

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
    await router.push('/')
  }

  const login = async (email: string, password: string): Promise<void> => {
    await initCsrf()
    const { data } = await apiFetch<{ data: User }>('/api/auth/login', {
      method: 'POST',
      body: { email, password },
    })
    authStore.setUser(data)
    await router.push('/')
  }

  const logout = async (): Promise<void> => {
    await apiFetch('/api/auth/logout', { method: 'POST' })
    authStore.clearUser()
    await router.push('/login')
  }

  return { login, register, logout, fetchUser }
}
