<script setup lang="ts">
definePageMeta({ middleware: 'auth' })

const moodStore = useMoodStore()

type InputMode = 'emoji' | 'slider'
const mode = ref<InputMode>('emoji')

const sliderValues = computed(() => ({
  energy:    moodStore.form.energy,
  valence:   moodStore.form.valence,
  intensity: moodStore.form.intensity,
}))

// Feature 1 — emoji click => playlist immediate, pas de bouton
async function onEmojiSelect(payload: { emotion: string; energy: number; valence: number; intensity: number }) {
  moodStore.setForm({
    source:    'emoji',
    emotion:   payload.emotion,
    energy:    payload.energy,
    valence:   payload.valence,
    intensity: payload.intensity,
  })
  await moodStore.analyze()
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

function switchMode(next: InputMode) {
  mode.value = next
  moodStore.setForm({ source: next, emotion: null })
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

// Feature 3 — sliders => gradient de fond dynamique
const moodBgGradient = computed(() => {
  const { energy, valence, intensity } = moodStore.form
  // Hue : 240° (bleu/triste) → 30° (orange/heureux) selon la valence
  const hue = Math.round(240 + ((valence + 1) / 2) * (30 - 240))
  const sat = Math.round(40 + (intensity / 100) * 40)  // 40% → 80%
  const light = Math.round(6 + (energy / 100) * 8)     // 6% → 14%
  const hue2 = (hue + 50) % 360
  return `radial-gradient(ellipse at 25% 25%, hsl(${hue}, ${sat}%, ${light + 4}%) 0%, transparent 55%), radial-gradient(ellipse at 75% 75%, hsl(${hue2}, ${sat - 10}%, ${light}%) 0%, transparent 55%), hsl(${hue}, 15%, 4%)`
})

// Sync vers le layout, reset au démontage
const moodBg = useState<string>('moodBg', () => '')
watchEffect(() => { moodBg.value = moodBgGradient.value })
onUnmounted(() => { moodBg.value = '' })

// Sync la playlist dans le store player quand les tracks arrivent
const playerStore = usePlayerStore()
watch(() => moodStore.tracks, (tracks) => {
  playerStore.setPlaylist(tracks)
})

const toast = useToast()
watch(() => moodStore.error, (err) => {
  if (err) toast.add({ title: 'Erreur', description: err, color: 'error' })
})
</script>

<template>
  <UContainer class="py-12 max-w-2xl">
    <!-- Header -->
    <div class="mb-10 text-center space-y-2">
      <h1 class="text-3xl font-bold text-white">Comment tu te sens ?</h1>
      <p class="text-gray-400 text-sm">Décris ton mood pour obtenir une playlist personnalisée.</p>
    </div>

    <UCard class="bg-gray-900/60 backdrop-blur border border-white/10">
      <div class="space-y-8">

        <!-- Toggle mode -->
        <div class="flex justify-center">
          <div class="inline-flex rounded-xl bg-white/5 p-1 gap-1">
            <button
              type="button"
              class="px-5 py-2 rounded-lg text-sm font-medium transition-all duration-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
              :class="mode === 'emoji'
                ? 'bg-primary-500 text-white shadow'
                : 'text-gray-400 hover:text-white'"
              @click="switchMode('emoji')"
            >
              Emoji
            </button>
            <button
              type="button"
              class="px-5 py-2 rounded-lg text-sm font-medium transition-all duration-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
              :class="mode === 'slider'
                ? 'bg-primary-500 text-white shadow'
                : 'text-gray-400 hover:text-white'"
              @click="switchMode('slider')"
            >
              Sliders
            </button>
          </div>
        </div>

        <!-- Input panels -->
        <Transition
          enter-active-class="transition duration-200 ease-out"
          enter-from-class="opacity-0 translate-y-2"
          enter-to-class="opacity-100 translate-y-0"
          leave-active-class="transition duration-150 ease-in"
          leave-from-class="opacity-100 translate-y-0"
          leave-to-class="opacity-0 -translate-y-2"
          mode="out-in"
        >
          <MoodEmojiPicker
            v-if="mode === 'emoji'"
            key="emoji"
            @select="onEmojiSelect"
          />
          <MoodSliders
            v-else
            key="slider"
            :model-value="sliderValues"
            @update:model-value="onSlidersUpdate"
          />
        </Transition>

        <!-- Feature 2 : feedback emoji/emotion pour le mode slider -->
        <Transition
          enter-active-class="transition duration-300"
          enter-from-class="opacity-0 scale-90"
          enter-to-class="opacity-100 scale-100"
          leave-active-class="transition duration-200"
          leave-from-class="opacity-100 scale-100"
          leave-to-class="opacity-0 scale-90"
        >
          <div
            v-if="mode === 'slider'"
            class="flex items-center justify-center gap-3 py-2"
          >
            <span class="text-4xl leading-none transition-all duration-300">{{ sliderMood.emoji }}</span>
            <span class="text-lg font-semibold text-white">{{ sliderMood.label }}</span>
          </div>
        </Transition>

        <!-- Emotion selectionnée (mode emoji) -->
        <Transition
          enter-active-class="transition duration-200"
          enter-from-class="opacity-0 scale-95"
          enter-to-class="opacity-100 scale-100"
          leave-active-class="transition duration-150"
          leave-from-class="opacity-100 scale-100"
          leave-to-class="opacity-0 scale-95"
        >
          <div
            v-if="mode === 'emoji' && moodStore.form.emotion"
            class="flex items-center justify-center gap-2 py-2"
          >
            <UBadge size="lg" color="primary" variant="soft">
              {{ moodStore.form.emotion }}
              — énergie {{ moodStore.form.energy }}%
              · intensité {{ moodStore.form.intensity }}%
            </UBadge>
          </div>
        </Transition>

        <!-- Bouton générer (slider uniquement — en emoji c'est auto) -->
        <UButton
          v-if="mode === 'slider'"
          size="xl"
          block
          :loading="moodStore.loading"
          :disabled="moodStore.loading"
          @click="moodStore.analyze()"
        >
          <UIcon name="i-heroicons-musical-note" class="w-5 h-5" />
          Générer ma playlist
        </UButton>

      </div>
    </UCard>

    <!-- Loading indicator mode emoji -->
    <Transition
      enter-active-class="transition duration-200"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
    >
      <div v-if="mode === 'emoji' && moodStore.loading" class="mt-8 flex justify-center">
        <div class="flex items-center gap-3 text-primary-400">
          <UIcon name="i-heroicons-musical-note" class="w-6 h-6 animate-bounce" />
          <span class="text-sm font-medium">Génération de ta playlist…</span>
        </div>
      </div>
    </Transition>

    <!-- Résultats -->
    <Transition
      enter-active-class="transition duration-300 ease-out"
      enter-from-class="opacity-0 translate-y-4"
      enter-to-class="opacity-100 translate-y-0"
    >
      <div v-if="moodStore.moodProfile" class="mt-10 space-y-4 pb-24">
        <!-- Mood résumé -->
        <div class="flex items-center justify-between">
          <div>
            <h2 class="text-xl font-bold text-white capitalize">
              {{ moodStore.moodProfile.emotion }}
            </h2>
            <p class="text-sm text-gray-400 mt-0.5">
              {{ moodStore.tracks.length }} tracks générées
            </p>
          </div>
          <div class="flex gap-2">
            <UBadge variant="soft" color="primary" size="sm">
              Énergie {{ moodStore.moodProfile.energy }}%
            </UBadge>
            <UBadge variant="soft" color="warning" size="sm">
              Intensité {{ moodStore.moodProfile.intensity }}%
            </UBadge>
          </div>
        </div>

        <!-- Liste des tracks -->
        <MusicTrackList :tracks="moodStore.tracks" />
      </div>
    </Transition>
  </UContainer>

  <!-- Player audio sticky en bas de page -->
  <MusicTrackPlayer />
</template>
