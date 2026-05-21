<!-- moodflow-client/app/pages/dna.vue -->
<script setup lang="ts">
definePageMeta({ middleware: 'auth' })

const dnaStore = useDnaStore()

const insufficientProgress = computed(() => ({
  current: dnaStore.insufficientData?.current ?? 0,
  minimum: dnaStore.insufficientData?.minimum ?? 10,
  percent: Math.round(
    (dnaStore.insufficientData?.current ?? 0) / (dnaStore.insufficientData?.minimum || 10) * 100,
  ),
}))

const dnaCanvas = ref<{ downloadImage: () => void } | null>(null)

onMounted(() => dnaStore.fetchDna())

// ── Description auto-generator ────────────────────────────────────────────────

const ENERGY_LABEL = {
  calm:      'calme',
  moderate:  'équilibré',
  energetic: 'énergique',
} as const

const VALENCE_LABEL = {
  melancholic: 'mélancolique',
  neutral:     'nuancé',
  positive:    'positif',
} as const

function peakHourLabel(hour: number | null): string {
  if (hour === null) return 'à toute heure'
  if (hour < 12) return 'le matin'
  if (hour < 18) return "l'après-midi"
  return 'le soir'
}

const description = computed((): string => {
  const dna = dnaStore.dna
  if (!dna) return ''
  const energy  = ENERGY_LABEL[dna.energyProfile]
  const valence = VALENCE_LABEL[dna.valenceProfile]
  const genres  = dna.genreFingerprint.slice(0, 2).join(' et ') || 'la musique'
  const hour    = peakHourLabel(dna.peakHour)
  const mode    = dna.activityProfile[0] ? `, souvent en mode ${dna.activityProfile[0]}` : ''
  return `Tu es un auditeur ${energy} et ${valence}, attiré par ${genres}. Tes moments musicaux préférés sont ${hour}${mode}.`
})

// ── Badges ────────────────────────────────────────────────────────────────────

type BadgeColor = 'primary' | 'secondary' | 'success' | 'neutral' | 'warning'

interface Badge { label: string; color: BadgeColor }

const badges = computed((): Badge[] => {
  const dna = dnaStore.dna
  if (!dna) return []
  const b: Badge[] = []
  b.push({ label: ENERGY_LABEL[dna.energyProfile],  color: 'primary' })
  b.push({ label: VALENCE_LABEL[dna.valenceProfile], color: 'secondary' })
  if (dna.dominantEmotion) b.push({ label: dna.dominantEmotion, color: 'success' })
  dna.genreFingerprint.slice(0, 2).forEach(g => b.push({ label: g, color: 'neutral' }))
  if (dna.activityProfile[0]) b.push({ label: dna.activityProfile[0], color: 'warning' })
  return b
})

// ── Actions ───────────────────────────────────────────────────────────────────

function downloadDna(): void {
  dnaCanvas.value?.downloadImage()
}
</script>

<template>
  <UContainer class="py-12 max-w-2xl">
    <!-- Loading -->
    <div v-if="dnaStore.loading" class="flex justify-center py-24" role="status" aria-label="Chargement...">
      <UIcon name="i-heroicons-arrow-path" class="w-10 h-10 animate-spin text-primary-500" />
    </div>

    <!-- Error -->
    <div v-else-if="dnaStore.error" class="text-center py-16">
      <UIcon name="i-heroicons-exclamation-circle" class="w-12 h-12 text-red-500 mx-auto mb-4" />
      <p class="text-gray-500">{{ dnaStore.error }}</p>
      <UButton class="mt-4" @click="dnaStore.fetchDna()">Réessayer</UButton>
    </div>

    <!-- Insufficient data -->
    <div v-else-if="dnaStore.isInsufficient" class="flex flex-col items-center gap-6 py-16">
      <div class="relative">
        <UIcon name="i-heroicons-lock-closed" class="w-20 h-20 text-gray-300 dark:text-gray-700" />
        <div class="absolute inset-0 flex items-center justify-center">
          <UIcon name="i-heroicons-musical-note" class="w-8 h-8 text-primary-500" />
        </div>
      </div>

      <div class="text-center">
        <h2 class="text-xl font-bold text-gray-900 dark:text-white">Ton Mood DNA n'est pas encore prêt</h2>
        <p class="text-gray-500 mt-2">
          Encore {{ dnaStore.remaining }} mood{{ dnaStore.remaining > 1 ? 's' : '' }} pour débloquer ton DNA
        </p>
      </div>

      <div class="w-full max-w-sm">
        <div class="flex justify-between text-sm text-gray-500 mb-2">
          <span>{{ insufficientProgress.current }} / {{ insufficientProgress.minimum }} moods</span>
          <span>{{ insufficientProgress.percent }}%</span>
        </div>
        <UProgress
          :value="insufficientProgress.current"
          :max="insufficientProgress.minimum"
          color="primary"
        />
      </div>

      <NuxtLink to="/mood">
        <UButton color="primary" icon="i-heroicons-face-smile">
          Analyser un mood
        </UButton>
      </NuxtLink>
    </div>

    <!-- DNA ready -->
    <div v-else-if="dnaStore.dna" class="flex flex-col items-center gap-8">
      <!-- Header -->
      <div class="text-center">
        <h1 class="text-3xl font-bold text-gray-900 dark:text-white">Ton Mood DNA</h1>
        <p class="text-sm text-gray-500 mt-1">Ton portrait émotionnel unique</p>
      </div>

      <!-- Canvas -->
      <DnaCanvas ref="dnaCanvas" :dna="dnaStore.dna" :size="400" />

      <!-- Description -->
      <UCard class="w-full">
        <p class="text-base text-gray-700 dark:text-gray-300 leading-relaxed text-center">
          {{ description }}
        </p>
      </UCard>

      <!-- Gene badges -->
      <div class="flex flex-wrap gap-2 justify-center">
        <UBadge
          v-for="badge in badges"
          :key="badge.label"
          :color="badge.color"
          variant="soft"
          size="md"
          class="capitalize"
        >
          {{ badge.label }}
        </UBadge>
      </div>

      <!-- Actions -->
      <div class="flex gap-3">
        <UButton
          color="neutral"
          variant="outline"
          icon="i-heroicons-arrow-down-tray"
          @click="downloadDna"
        >
          Partager
        </UButton>
        <UButton
          color="primary"
          variant="outline"
          icon="i-heroicons-arrow-path"
          :loading="dnaStore.loading"
          @click="dnaStore.fetchDna()"
        >
          Actualiser
        </UButton>
      </div>
    </div>
  </UContainer>
</template>
