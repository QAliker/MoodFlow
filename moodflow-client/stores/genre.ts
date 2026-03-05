import { defineStore } from 'pinia'

interface Genre {
  id: number
  name: string
  slug: string
  icon: string
  deezer_id: number | null
}

export const useGenreStore = defineStore('genre', () => {
  const config = useRuntimeConfig()
  const genres = ref<Genre[]>([])
  const loading = ref(false)

  async function fetchGenres(): Promise<void> {
    loading.value = true
    try {
      const response = await $fetch<{ data: Genre[] }>('/api/genres', {
        baseURL: config.public.apiUrl,
        credentials: 'include',
      })
      genres.value = response.data
    } finally {
      loading.value = false
    }
  }

  async function updateUserGenres(genreIds: number[]): Promise<void> {
    await $fetch('/api/users/me/genres', {
      baseURL: config.public.apiUrl,
      method: 'PUT',
      credentials: 'include',
      body: { genre_ids: genreIds },
    })
  }

  return { genres, loading, fetchGenres, updateUserGenres }
})
