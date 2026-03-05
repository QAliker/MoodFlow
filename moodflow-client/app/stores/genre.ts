import { defineStore } from 'pinia'
import type { Genre } from '~/stores/auth'

export const useGenreStore = defineStore('genre', () => {
  const apiFetch = useApiFetch()
  const genres = ref<Genre[]>([])
  const loading = ref(false)

  async function fetchGenres(): Promise<void> {
    loading.value = true
    try {
      const response = await apiFetch<{ data: Genre[] }>('/api/genres')
      genres.value = response.data
    } finally {
      loading.value = false
    }
  }

  async function updateUserGenres(genreIds: number[]): Promise<void> {
    await apiFetch('/api/users/me/genres', {
      method: 'PUT',
      body: { genre_ids: genreIds },
    })
  }

  return { genres, loading, fetchGenres, updateUserGenres }
})
