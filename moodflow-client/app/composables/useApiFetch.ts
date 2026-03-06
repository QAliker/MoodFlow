const MUTATING_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE']

export const useApiFetch = () => {
  const config = useRuntimeConfig()

  const getXsrfToken = (): string => {
    if (!import.meta.client) return ''
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]*)/)
    return match ? decodeURIComponent(match[1]) : ''
  }

  const ensureCsrf = async (): Promise<void> => {
    if (!import.meta.client) return
    if (getXsrfToken()) return
    await $fetch('/sanctum/csrf-cookie', {
      baseURL: config.public.apiUrl,
      credentials: 'include',
    })
  }

  return $fetch.create({
    baseURL: config.public.apiUrl,
    credentials: 'include',
    headers: { Accept: 'application/json' },
    async onRequest({ options }) {
      const method = (options.method ?? 'GET').toString().toUpperCase()
      if (MUTATING_METHODS.includes(method)) {
        await ensureCsrf()
      }
      const token = getXsrfToken()
      if (token) {
        options.headers = { ...options.headers as Record<string, string>, 'X-XSRF-TOKEN': token }
      }
    },
  })
}
