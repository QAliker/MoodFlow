<script setup lang="ts">
interface SliderValues {
  energy: number    // 0-100
  valence: number   // -1 to 1
  intensity: number // 0-100
}

const props = defineProps<{ modelValue: SliderValues }>()

const emit = defineEmits<{
  'update:modelValue': [value: SliderValues]
}>()

// valence is stored as -1..1 in the store but displayed as -100..100 in the slider
const valenceDisplay = computed({
  get: () => Math.round(props.modelValue.valence * 100),
  set: (v: number) => update('valence', v / 100),
})

const energyDisplay = computed({
  get: () => props.modelValue.energy,
  set: (v: number) => update('energy', v),
})

const intensityDisplay = computed({
  get: () => props.modelValue.intensity,
  set: (v: number) => update('intensity', v),
})

function update(key: keyof SliderValues, value: number) {
  emit('update:modelValue', { ...props.modelValue, [key]: value })
}

// Gradient background fill percentage for the decorative bar
function fillPercent(value: number, min: number, max: number): string {
  return `${((value - min) / (max - min)) * 100}%`
}
</script>

<template>
  <div class="space-y-8">
    <!-- Énergie -->
    <div class="space-y-3">
      <div class="flex justify-between items-center">
        <div class="flex items-center gap-2">
          <span class="text-lg">⚡</span>
          <span class="text-sm font-semibold text-gray-200">Énergie</span>
        </div>
        <UBadge variant="soft" color="primary" size="sm">{{ modelValue.energy }}</UBadge>
      </div>
      <div class="relative">
        <!-- Gradient track décoratif -->
        <div class="absolute inset-x-0 top-1/2 -translate-y-1/2 h-1.5 rounded-full overflow-hidden pointer-events-none bg-white/5">
          <div
            class="h-full rounded-full transition-all duration-150"
            style="background: linear-gradient(to right, #3b82f6, #ef4444)"
            :style="{ width: fillPercent(modelValue.energy, 0, 100) }"
          />
        </div>
        <USlider v-model="energyDisplay" :min="0" :max="100" :step="1" color="primary" />
      </div>
      <div class="flex justify-between text-xs text-gray-500">
        <span>Calme</span>
        <span>Explosif</span>
      </div>
    </div>

    <!-- Valence -->
    <div class="space-y-3">
      <div class="flex justify-between items-center">
        <div class="flex items-center gap-2">
          <span class="text-lg">🎭</span>
          <span class="text-sm font-semibold text-gray-200">Valence</span>
        </div>
        <UBadge
          variant="soft"
          :color="modelValue.valence >= 0 ? 'warning' : 'secondary'"
          size="sm"
        >
          {{ modelValue.valence >= 0 ? '+' : '' }}{{ valenceDisplay }}
        </UBadge>
      </div>
      <div class="relative">
        <div class="absolute inset-x-0 top-1/2 -translate-y-1/2 h-1.5 rounded-full overflow-hidden pointer-events-none bg-white/5">
          <div
            class="h-full rounded-full transition-all duration-150"
            :style="{
              background: 'linear-gradient(to right, #7c3aed, #eab308)',
              width: fillPercent(valenceDisplay, -100, 100),
            }"
          />
        </div>
        <USlider v-model="valenceDisplay" :min="-100" :max="100" :step="1" color="warning" />
      </div>
      <div class="flex justify-between text-xs text-gray-500">
        <span>Négatif</span>
        <span>Positif</span>
      </div>
    </div>

    <!-- Intensité -->
    <div class="space-y-3">
      <div class="flex justify-between items-center">
        <div class="flex items-center gap-2">
          <span class="text-lg">🌊</span>
          <span class="text-sm font-semibold text-gray-200">Intensité</span>
        </div>
        <UBadge variant="soft" color="error" size="sm">{{ modelValue.intensity }}</UBadge>
      </div>
      <div class="relative">
        <div class="absolute inset-x-0 top-1/2 -translate-y-1/2 h-1.5 rounded-full overflow-hidden pointer-events-none bg-white/5">
          <div
            class="h-full rounded-full transition-all duration-150"
            :style="{
              background: 'linear-gradient(to right, #6b7280, #f97316)',
              width: fillPercent(modelValue.intensity, 0, 100),
            }"
          />
        </div>
        <USlider v-model="intensityDisplay" :min="0" :max="100" :step="1" color="error" />
      </div>
      <div class="flex justify-between text-xs text-gray-500">
        <span>Léger</span>
        <span>Intense</span>
      </div>
    </div>
  </div>
</template>
