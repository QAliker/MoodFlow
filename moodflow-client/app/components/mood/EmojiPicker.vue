<script setup lang="ts">
interface EmojiOption {
  emoji: string
  label: string
  energy: number    // 0-100
  valence: number   // -1 to 1
  intensity: number // 0-100
}

const EMOJIS: EmojiOption[] = [
  { emoji: '😊', label: 'Joyeux',       energy: 60, valence:  0.8,  intensity: 50 },
  { emoji: '😢', label: 'Triste',        energy: 20, valence: -0.7,  intensity: 30 },
  { emoji: '😡', label: 'En colère',     energy: 80, valence: -0.8,  intensity: 80 },
  { emoji: '🥳', label: 'Festif',        energy: 90, valence:  0.9,  intensity: 90 },
  { emoji: '😌', label: 'Serein',        energy: 25, valence:  0.5,  intensity: 20 },
  { emoji: '🔥', label: 'Intense',       energy: 95, valence:  0.3,  intensity: 95 },
  { emoji: '💔', label: 'Mélancolique',  energy: 15, valence: -0.9,  intensity: 40 },
  { emoji: '😴', label: 'Endormi',       energy: 10, valence:  0.0,  intensity: 10 },
]

const emit = defineEmits<{
  select: [payload: { emotion: string; energy: number; valence: number; intensity: number }]
}>()

const selected = ref<string | null>(null)

function pick(option: EmojiOption) {
  selected.value = option.emoji
  emit('select', {
    emotion:   option.label,
    energy:    option.energy,
    valence:   option.valence,
    intensity: option.intensity,
  })
}
</script>

<template>
  <div class="grid grid-cols-4 gap-3">
    <button
      v-for="option in EMOJIS"
      :key="option.emoji"
      type="button"
      :aria-pressed="selected === option.emoji"
      class="group flex flex-col items-center gap-2 rounded-2xl p-4 transition-all duration-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2 focus-visible:ring-offset-gray-950 select-none cursor-pointer"
      :class="selected === option.emoji
        ? 'bg-primary-500 shadow-lg shadow-primary-500/30 scale-105'
        : 'bg-white/5 hover:bg-white/10 border border-white/5 hover:border-white/15 hover:scale-105'"
      @click="pick(option)"
    >
      <span class="text-4xl leading-none transition-transform duration-200 group-hover:scale-110 group-active:scale-90">
        {{ option.emoji }}
      </span>
      <span
        class="text-xs font-medium leading-tight text-center transition-colors"
        :class="selected === option.emoji ? 'text-white' : 'text-gray-400 group-hover:text-white'"
      >
        {{ option.label }}
      </span>
    </button>
  </div>
</template>
