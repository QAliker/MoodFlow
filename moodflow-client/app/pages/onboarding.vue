<script setup lang="ts">
import { useGenreStore } from '~/stores/genre'

definePageMeta({ middleware: 'auth', layout: false })

const genreStore = useGenreStore()
const router = useRouter()
const toast = useToast()

const selectedIds = ref<number[]>([])
const submitting = ref(false)

const MIN = 3
const MAX = 5
const isValid = computed(() => selectedIds.value.length >= MIN && selectedIds.value.length <= MAX)

const { data, error } = await useFetch<{ data: typeof genreStore.genres }>('/api/genres', {
  baseURL: useRuntimeConfig().public.apiUrl,
  credentials: 'include',
})
if (data.value) genreStore.genres = data.value.data

async function submit(): Promise<void> {
  if (!isValid.value) return
  submitting.value = true
  try {
    await genreStore.updateUserGenres(selectedIds.value)
    toast.add({ title: 'Preferences sauvegardees !', color: 'success', icon: 'i-heroicons-musical-note' })
    router.push('/mood')
  } catch {
    toast.add({ title: 'Erreur de sauvegarde', description: 'Reessaie dans un instant.', color: 'error' })
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <div class="min-h-screen bg-gradient-to-br from-gray-950 via-gray-900 to-primary-950 flex flex-col items-center justify-center px-4 py-12 relative overflow-hidden">
    <!-- Background decorations -->
    <div class="absolute inset-0 overflow-hidden pointer-events-none" aria-hidden="true">
      <div class="absolute -top-40 -right-40 w-96 h-96 bg-primary-500/10 rounded-full blur-3xl" />
      <div class="absolute -bottom-40 -left-40 w-96 h-96 bg-primary-700/10 rounded-full blur-3xl" />
      <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[600px] h-[600px] bg-primary-600/5 rounded-full blur-3xl" />
    </div>

    <div class="relative w-full max-w-2xl flex flex-col gap-8">
      <!-- Header -->
      <div class="text-center space-y-3">
        <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-primary-500/20 ring-1 ring-primary-500/30 mb-2">
          <UIcon name="i-heroicons-musical-note" class="w-8 h-8 text-primary-400" />
        </div>
        <h1 class="text-3xl font-bold text-white tracking-tight">
          Tes genres musicaux
        </h1>
        <p class="text-gray-400 max-w-md mx-auto leading-relaxed">
          Choisis entre <span class="text-primary-400 font-medium">3 et 5 genres</span> pour que MoodFlow puisse te recommander la musique parfaite selon ton humeur.
        </p>
      </div>

      <!-- Card -->
      <div class="bg-gray-900/80 backdrop-blur-xl border border-white/10 rounded-3xl p-6 sm:p-8 shadow-2xl shadow-black/40">
        <template v-if="genreStore.genres.length > 0">
          <GenreSelector v-model="selectedIds" :genres="genreStore.genres" />
        </template>
        <template v-else>
          <div class="flex flex-col items-center justify-center py-16 gap-3 text-gray-500">
            <UIcon name="i-heroicons-arrow-path" class="w-8 h-8 animate-spin" />
            <span class="text-sm">Chargement des genres...</span>
          </div>
        </template>
      </div>

      <!-- Footer actions -->
      <div class="flex items-center justify-between">
        <p class="text-xs text-gray-600">
          Tu pourras modifier tes genres dans les reglages
        </p>
        <UButton
          size="xl"
          :disabled="!isValid || submitting"
          :loading="submitting"
          :color="isValid ? 'primary' : 'neutral'"
          trailing-icon="i-heroicons-arrow-right-20-solid"
          class="transition-all duration-300"
          :class="isValid ? 'shadow-lg shadow-primary-500/30' : ''"
          @click="submit"
        >
          {{ isValid ? 'Commencer' : `Encore ${MIN - selectedIds.length} genre${MIN - selectedIds.length > 1 ? 's' : ''}` }}
        </UButton>
      </div>
    </div>

    <UToaster />
  </div>
</template>
