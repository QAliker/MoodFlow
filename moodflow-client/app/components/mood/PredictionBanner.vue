<script setup lang="ts">
import type { MoodPrediction } from '~/stores/mood'

defineProps<{ prediction: MoodPrediction }>()

const emit = defineEmits<{
  apply: []
  dismiss: []
}>()
</script>

<template>
  <Transition
    enter-active-class="transition duration-500 ease-out"
    enter-from-class="opacity-0 -translate-y-2"
    enter-to-class="opacity-100 translate-y-0"
    appear
  >
    <UAlert
      icon="i-heroicons-sparkles"
      color="primary"
      variant="subtle"
      :title="`🔮 ${prediction.message}`"
      description="Utiliser cette prédiction ?"
      orientation="horizontal"
    >
      <template #actions>
        <UButton size="sm" color="primary" variant="solid" @click="emit('apply')">
          Oui
        </UButton>
        <UButton size="sm" color="neutral" variant="ghost" @click="emit('dismiss')">
          Non merci
        </UButton>
      </template>
    </UAlert>
  </Transition>
</template>
