export const useApiFetch = () => {
  const config = useRuntimeConfig()

  const getXsrfToken = (): string => {
    if (!import.meta.client) return ''
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]*)/)
    return match ? decodeURIComponent(match[1]) : ''
  }

  return $fetch.create({
    baseURL: config.public.apiUrl,
    credentials: 'include',
    headers: { Accept: 'application/json' },
    onRequest({ options }) {
      const token = getXsrfToken()
      if (token) {
        options.headers = { ...options.headers as Record<string, string>, 'X-XSRF-TOKEN': token }
      }
    },
  })
}
