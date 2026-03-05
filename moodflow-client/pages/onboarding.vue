<script setup lang="ts">
import { useGenreStore } from '~/stores/genre'

const genreStore = useGenreStore()
const router = useRouter()
const toast = useToast()

const selectedGenreIds = ref<number[]>([])
const submitting = ref(false)

const MIN_GENRES = 3
const MAX_GENRES = 5

const isValid = computed(
  () => selectedGenreIds.value.length >= MIN_GENRES && selectedGenreIds.value.length <= MAX_GENRES,
)

await genreStore.fetchGenres()

async function submit(): Promise<void> {
  if (!isValid.value) return

  submitting.value = true
  try {
    await genreStore.updateUserGenres(selectedGenreIds.value)
    toast.add({
      title: 'Genres enregistres !',
      description: 'Tes preferences musicales ont ete sauvegardees.',
      color: 'success',
    })
    router.push('/mood')
  } catch {
    toast.add({
      title: 'Erreur',
      description: 'Impossible de sauvegarder tes genres. Reessaie.',
      color: 'error',
    })
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <div class="min-h-screen flex items-center justify-center bg-gray-50 dark:bg-gray-950 px-4 py-12">
    <div class="w-full max-w-2xl">
      <UCard class="shadow-xl">
        <template #header>
          <div class="text-center space-y-2">
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
              Choisis tes genres
            </h1>
            <p class="text-gray-500 dark:text-gray-400">
              Selectionne entre 3 et 5 genres musicaux pour personnaliser ton experience MoodFlow
            </p>
          </div>
        </template>

        <GenreSelector
          v-model="selectedGenreIds"
          :genres="genreStore.genres"
        />

        <template #footer>
          <div class="flex justify-end">
            <UButton
              size="lg"
              :disabled="!isValid || submitting"
              :loading="submitting"
              icon="i-heroicons-musical-note"
              @click="submit"
            >
              Commencer l'experience
            </UButton>
          </div>
        </template>
      </UCard>
    </div>
  </div>
</template>
