<script setup lang="ts">
interface ActivityMode {
  slug: string
  label: string
  emoji: string
  energy: number
  valence: number
  intensity: number
}

const ACTIVITY_MODES: ActivityMode[] = [
  { slug: 'focus',      label: 'Concentration', emoji: '🎯', energy: 40, valence:  0.2, intensity: 30 },
  { slug: 'sleep',      label: 'Sommeil',        emoji: '😴', energy: 10, valence:  0.1, intensity: 10 },
  { slug: 'workout',    label: 'Sport',          emoji: '💪', energy: 90, valence:  0.5, intensity: 85 },
  { slug: 'morning',    label: 'Matin',          emoji: '🌅', energy: 55, valence:  0.6, intensity: 45 },
  { slug: 'destress',   label: 'Décompression',  emoji: '🧘', energy: 25, valence:  0.3, intensity: 20 },
  { slug: 'commute',    label: 'Trajet',         emoji: '🚇', energy: 50, valence:  0.3, intensity: 50 },
  { slug: 'motivation', label: 'Motivation',     emoji: '🚀', energy: 80, valence:  0.7, intensity: 75 },
  { slug: 'soiree',     label: 'Soirée',         emoji: '🎉', energy: 85, valence:  0.8, intensity: 80 },
  { slug: 'meditation', label: 'Méditation',     emoji: '☯️', energy:  5, valence:  0.2, intensity:  5 },
  { slug: 'cuisiner',   label: 'Cuisine',        emoji: '👨‍🍳', energy: 50, valence:  0.5, intensity: 40 },
  { slug: 'rainy_day',  label: 'Jour de pluie',  emoji: '🌧️', energy: 30, valence: -0.1, intensity: 40 },
]

const props = defineProps<{ modelValue: string | null }>()

const emit = defineEmits<{
  'update:modelValue': [slug: string | null]
  defaults: [values: { energy: number; valence: number; intensity: number }]
}>()

function select(mode: ActivityMode) {
  emit('update:modelValue', mode.slug)
  emit('defaults', { energy: mode.energy, valence: mode.valence, intensity: mode.intensity })
}

function clear() {
  emit('update:modelValue', null)
}
</script>

<template>
  <div class="space-y-5">
    <div class="grid grid-cols-3 sm:grid-cols-4 gap-3">
      <button
        v-for="mode in ACTIVITY_MODES"
        :key="mode.slug"
        type="button"
        :aria-pressed="modelValue === mode.slug"
        class="group flex flex-col items-center gap-2 rounded-2xl p-4 transition-all duration-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2 focus-visible:ring-offset-gray-950 select-none cursor-pointer"
        :class="modelValue === mode.slug
          ? 'bg-primary-500 shadow-lg shadow-primary-500/30 scale-105'
          : 'bg-white/5 hover:bg-white/10 border border-white/5 hover:border-white/15 hover:scale-105'"
        @click="select(mode)"
      >
        <span class="text-3xl leading-none transition-transform duration-200 group-hover:scale-110 group-active:scale-90">
          {{ mode.emoji }}
        </span>
        <span
          class="text-xs font-medium leading-tight text-center transition-colors"
          :class="modelValue === mode.slug ? 'text-white' : 'text-gray-400 group-hover:text-white'"
        >
          {{ mode.label }}
        </span>
      </button>
    </div>

    <Transition
      enter-active-class="transition duration-200 ease-out"
      enter-from-class="opacity-0 translate-y-1"
      enter-to-class="opacity-100 translate-y-0"
      leave-active-class="transition duration-150 ease-in"
      leave-from-class="opacity-100 translate-y-0"
      leave-to-class="opacity-0 translate-y-1"
    >
      <div v-if="modelValue" class="flex justify-center">
        <UButton
          variant="ghost"
          color="neutral"
          size="sm"
          icon="i-heroicons-x-mark"
          @click="clear"
        >
          Mood pur
        </UButton>
      </div>
    </Transition>
  </div>
</template>
