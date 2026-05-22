<script setup lang="ts">
definePageMeta({ middleware: 'auth' })

const moodStore = useMoodStore()
const { weather, coords, fetch: fetchWeather } = useWeather()

onMounted(fetchWeather)

type InputTab = 'emoji' | 'slider' | 'activity'

const tabs = [
  { slot: 'emoji',    label: 'Emojis',    icon: 'i-heroicons-face-smile' },
  { slot: 'slider',   label: 'Sliders',   icon: 'i-heroicons-adjustments-horizontal' },
  { slot: 'activity', label: 'Activité',  icon: 'i-heroicons-bolt' },
]

const activeTab = ref<InputTab>('emoji')

const sliderValues = computed(() => ({
  energy:    moodStore.form.energy,
  valence:   moodStore.form.valence,
  intensity: moodStore.form.intensity,
}))

async function onEmojiSelect(payload: { emotion: string; energy: number; valence: number; intensity: number }) {
  moodStore.setForm({
    source:    'emoji',
    emotion:   payload.emotion,
    energy:    payload.energy,
    valence:   payload.valence,
    intensity: payload.intensity,
  })
  await moodStore.analyze(coords.value)
}

function onSlidersUpdate(values: { energy: number; valence: number; intensity: number }) {
  moodStore.setForm({
    source:    'slider',
    emotion:   null,
    energy:    values.energy,
    valence:   values.valence,
    intensity: values.intensity,
  })
}

function onActivityDefaults(values: { energy: number; valence: number; intensity: number }) {
  moodStore.setForm({
    energy:    values.energy,
    valence:   values.valence,
    intensity: values.intensity,
  })
}

// Feature 2 — sliders => emoji/emotion dynamique
interface SliderMood { emoji: string; label: string }

const sliderMood = computed((): SliderMood => {
  const { energy, valence } = moodStore.form
  if (energy >= 70 && valence >= 0.3)  return { emoji: '🔥', label: 'Intense' }
  if (energy >= 70 && valence <= -0.3) return { emoji: '😡', label: 'En colère' }
  if (energy >= 70)                    return { emoji: '⚡', label: 'Agité' }
  if (energy >= 35 && valence >= 0.3)  return { emoji: '😊', label: 'Joyeux' }
  if (energy >= 35 && valence <= -0.3) return { emoji: '😔', label: 'Mélancolique' }
  if (energy >= 35)                    return { emoji: '😐', label: 'Neutre' }
  if (valence >= 0.3)                  return { emoji: '😌', label: 'Serein' }
  if (valence <= -0.3)                 return { emoji: '💔', label: 'Triste' }
  return { emoji: '😴', label: 'Endormi' }
})

// Feature 3 — canvas réactif
const visualEnergy    = computed(() => moodStore.moodProfile?.energy    ?? moodStore.form.energy)
const visualValence   = computed(() => moodStore.moodProfile?.valence   ?? moodStore.form.valence)
const visualIntensity = computed(() => moodStore.moodProfile?.intensity ?? moodStore.form.intensity)

const showOverlay = computed(() => moodStore.loading)

const playerStore = usePlayerStore()
watch(() => moodStore.tracks, (tracks) => {
  playerStore.setPlaylist(tracks)
})

const toast = useToast()

watch(() => moodStore.error, (err) => {
  if (err) toast.add({ title: 'Erreur', description: err, color: 'error' })
})

watch(() => moodStore.suggestedMode, (suggested) => {
  if (!suggested || moodStore.activityMode) return
  toast.add({
    title: 'Il pleut dehors 🌧️ — Mode Rainy Day suggéré',
    color: 'info',
    actions: [{
      label: 'Activer',
      onClick: () => {
        moodStore.setActivityMode('rainy_day', { energy: 30, valence: -0.1, intensity: 40 })
        moodStore.suggestedMode = null
      },
    }],
  })
})

// Label/emoji du mode actif pour le badge
const ACTIVITY_MODE_META: Record<string, { label: string; emoji: string }> = {
  focus:      { label: 'Concentration', emoji: '🎯' },
  sleep:      { label: 'Sommeil',       emoji: '😴' },
  workout:    { label: 'Sport',         emoji: '💪' },
  morning:    { label: 'Matin',         emoji: '🌅' },
  destress:   { label: 'Décompression', emoji: '🧘' },
  commute:    { label: 'Trajet',        emoji: '🚇' },
  motivation: { label: 'Motivation',    emoji: '🚀' },
  soiree:     { label: 'Soirée',        emoji: '🎉' },
  meditation: { label: 'Méditation',    emoji: '☯️' },
  cuisiner:   { label: 'Cuisine',       emoji: '👨‍🍳' },
  rainy_day:  { label: 'Jour de pluie', emoji: '🌧️' },
}

const activeModeMeta = computed(() =>
  moodStore.activityMode ? ACTIVITY_MODE_META[moodStore.activityMode] ?? null : null,
)
</script>

<template>
  <PageLoadingOverlay :show="showOverlay" />

  <!-- Feature 3 — canvas particules réactif au mood -->
  <VisualMoodCanvas
    :energy="visualEnergy"
    :valence="visualValence"
    :intensity="visualIntensity"
  />

  <UContainer class="py-12 max-w-2xl">
    <!-- Header -->
    <div class="mb-10 text-center space-y-2">
      <h1 class="text-3xl font-bold text-white">Comment tu te sens ?</h1>
      <p class="text-gray-400 text-sm">Décris ton mood pour obtenir une playlist personnalisée.</p>
    </div>

    <UCard class="bg-gray-900/60 backdrop-blur border border-white/10">
      <div class="space-y-8">

        <!-- Tabs -->
        <UTabs
          :items="tabs"
          :default-value="activeTab"
          @update:model-value="(v) => activeTab = v as InputTab"
        >
          <template #emoji>
            <div class="space-y-6 pt-4">
              <MoodEmojiPicker @select="onEmojiSelect" />

              <!-- Emotion sélectionnée -->
              <Transition
                enter-active-class="transition duration-200"
                enter-from-class="opacity-0 scale-95"
                enter-to-class="opacity-100 scale-100"
                leave-active-class="transition duration-150"
                leave-from-class="opacity-100 scale-100"
                leave-to-class="opacity-0 scale-95"
              >
                <div
                  v-if="moodStore.form.emotion"
                  class="flex items-center justify-center gap-2 py-2"
                >
                  <UBadge size="lg" color="primary" variant="soft">
                    {{ moodStore.form.emotion }}
                    — énergie {{ moodStore.form.energy }}%
                    · intensité {{ moodStore.form.intensity }}%
                  </UBadge>
                </div>
              </Transition>
            </div>
          </template>

          <template #slider>
            <div class="space-y-6 pt-4">
              <MoodSliders
                :model-value="sliderValues"
                @update:model-value="onSlidersUpdate"
              />

              <!-- Feature 2 : feedback emoji/emotion -->
              <div class="flex items-center justify-center gap-3 py-2">
                <span class="text-4xl leading-none transition-all duration-300">{{ sliderMood.emoji }}</span>
                <span class="text-lg font-semibold text-white">{{ sliderMood.label }}</span>
              </div>

              <UButton
                size="xl"
                block
                :loading="moodStore.loading"
                :disabled="moodStore.loading"
                @click="moodStore.analyze(coords)"
              >
                <UIcon name="i-heroicons-musical-note" class="w-5 h-5" />
                Générer ma playlist
              </UButton>
            </div>
          </template>

          <template #activity>
            <div class="space-y-6 pt-4">
              <MoodActivityModeSelector
                :model-value="moodStore.activityMode"
                @update:model-value="(slug) => slug ? moodStore.setActivityMode(slug) : moodStore.clearActivityMode()"
                @defaults="onActivityDefaults"
              />

              <UButton
                v-if="moodStore.activityMode"
                size="xl"
                block
                :loading="moodStore.loading"
                :disabled="moodStore.loading"
                @click="moodStore.analyze(coords)"
              >
                <UIcon name="i-heroicons-musical-note" class="w-5 h-5" />
                Générer ma playlist
              </UButton>
            </div>
          </template>
        </UTabs>

        <!-- Badge mode actif (visible dans tous les tabs) -->
        <Transition
          enter-active-class="transition duration-200 ease-out"
          enter-from-class="opacity-0 scale-95"
          enter-to-class="opacity-100 scale-100"
          leave-active-class="transition duration-150 ease-in"
          leave-from-class="opacity-100 scale-100"
          leave-to-class="opacity-0 scale-95"
        >
          <div v-if="activeModeMeta" class="flex justify-center">
            <UBadge
              size="md"
              color="primary"
              variant="soft"
              class="cursor-pointer gap-1.5"
              @click="moodStore.clearActivityMode()"
            >
              {{ activeModeMeta.emoji }} {{ activeModeMeta.label }}
              <UIcon name="i-heroicons-x-mark" class="w-3.5 h-3.5" />
            </UBadge>
          </div>
        </Transition>

      </div>
    </UCard>

    <!-- Résultats -->
    <div
      v-show="moodStore.moodProfile"
      class="mt-10 space-y-4 pb-24 transition-all duration-300 ease-out"
      :class="moodStore.moodProfile ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-4'"
    >
        <!-- Mood résumé -->
        <div class="flex items-center justify-between">
          <div>
            <h2 class="text-xl font-bold text-white capitalize">
              {{ moodStore.moodProfile?.emotion }}
            </h2>
            <p class="text-sm text-gray-400 mt-0.5">
              {{ moodStore.tracks.length }} tracks générées
            </p>
          </div>
          <div class="flex gap-2">
            <UBadge variant="soft" color="primary" size="sm">
              Énergie {{ moodStore.moodProfile?.energy }}%
            </UBadge>
            <UBadge variant="soft" color="warning" size="sm">
              Intensité {{ moodStore.moodProfile?.intensity }}%
            </UBadge>
          </div>
        </div>

        <!-- Liste des tracks -->
        <MusicTrackList :tracks="moodStore.tracks" />
      </div>
  </UContainer>

  <!-- Player audio sticky en bas de page -->
  <MusicTrackPlayer />
</template>
