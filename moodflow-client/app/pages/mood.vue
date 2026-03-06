<script setup lang="ts">
import type { MoodForm } from '~/stores/mood'

definePageMeta({ middleware: 'auth' })

const moodStore = useMoodStore()

type InputMode = 'emoji' | 'slider'
const mode = ref<InputMode>('emoji')

const sliderValues = computed(() => ({
  energy:    moodStore.form.energy,
  valence:   moodStore.form.valence,
  intensity: moodStore.form.intensity,
}))

function onEmojiSelect(payload: { emotion: string; energy: number; valence: number; intensity: number }) {
  moodStore.setForm({
    source:   'emoji',
    emotion:  payload.emotion,
    energy:   payload.energy,
    valence:  payload.valence,
    intensity: payload.intensity,
  })
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

async function generate() {
  await moodStore.analyze()
}

const hasSelection = computed(() =>
  mode.value === 'slider' || moodStore.form.emotion !== null,
)

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

        <!-- Émotion sélectionnée (mode emoji) -->
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

        <!-- Bouton générer -->
        <UButton
          size="xl"
          block
          :loading="moodStore.loading"
          :disabled="!hasSelection || moodStore.loading"
          @click="generate"
        >
          <UIcon name="i-heroicons-musical-note" class="w-5 h-5" />
          Générer ma playlist
        </UButton>
      </div>
    </UCard>

    <!-- Résultats -->
    <Transition
      enter-active-class="transition duration-300 ease-out"
      enter-from-class="opacity-0 translate-y-4"
      enter-to-class="opacity-100 translate-y-0"
    >
      <div v-if="moodStore.moodProfile" class="mt-10 space-y-6">
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

        <!-- Tracks -->
        <div class="space-y-3">
          <div
            v-for="track in moodStore.tracks"
            :key="track.deezer_id"
            class="flex items-center gap-4 p-4 rounded-2xl bg-gray-900/60 border border-white/5 hover:border-white/15 transition-colors group"
          >
            <img
              :src="track.cover_url"
              :alt="track.album"
              class="w-12 h-12 rounded-xl object-cover flex-shrink-0"
            >
            <div class="flex-1 min-w-0">
              <p class="text-sm font-semibold text-white truncate">{{ track.title }}</p>
              <p class="text-xs text-gray-400 truncate">{{ track.artist }}</p>
            </div>
            <UButton
              v-if="track.preview_url"
              :to="track.preview_url"
              target="_blank"
              variant="ghost"
              color="neutral"
              size="sm"
              icon="i-heroicons-play-circle"
              class="opacity-0 group-hover:opacity-100 transition-opacity"
            />
          </div>
        </div>
      </div>
    </Transition>
  </UContainer>
</template>
