<script setup lang="ts">
definePageMeta({ middleware: 'auth' })

const authStore = useAuthStore()
const moodStore = useMoodStore()

onMounted(() => moodStore.fetchHistory())

const lastMood    = computed(() => moodStore.history[0] ?? null)
const historyTail = computed(() => moodStore.history.slice(1, 6))
const hasHistory  = computed(() => moodStore.history.length > 0)

const visualEnergy    = computed(() => lastMood.value?.energy    ?? 50)
const visualValence   = computed(() => lastMood.value?.valence   ?? 0)
const visualIntensity = computed(() => lastMood.value?.intensity ?? 50)
</script>

<template>
  <VisualMoodCanvas
    :energy="visualEnergy"
    :valence="visualValence"
    :intensity="visualIntensity"
  />

  <UContainer class="py-10 max-w-5xl">
    <div class="flex flex-col gap-6">
      <DashboardHeroSection
        :user-name="authStore.user?.name ?? ''"
        :has-history="hasHistory"
      />

      <Transition
        enter-active-class="transition duration-300 ease-out"
        enter-from-class="opacity-0 translate-y-4"
        enter-to-class="opacity-100 translate-y-0"
      >
        <div v-if="hasHistory" class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <DashboardLastSessionCard :mood="lastMood!" />
          <DashboardMoodHistoryList
            v-if="historyTail.length"
            :history="historyTail"
          />
        </div>
      </Transition>

      <!-- Premier user — pas encore de session -->
      <Transition
        enter-active-class="transition duration-300 ease-out"
        enter-from-class="opacity-0 translate-y-4"
        enter-to-class="opacity-100 translate-y-0"
      >
        <div
          v-if="!hasHistory && !moodStore.loading"
          class="flex flex-col items-center gap-3 py-12 text-center"
        >
          <span class="text-5xl">🎧</span>
          <p class="text-gray-500 text-sm">
            Ton historique apparaîtra ici après ta première session.
          </p>
        </div>
      </Transition>
    </div>
  </UContainer>

  <MusicTrackPlayer />
</template>
