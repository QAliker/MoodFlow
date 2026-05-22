<script setup lang="ts">
const props = defineProps<{
  energy: number
  valence: number
  intensity: number
}>()

const emit = defineEmits<{ ready: [] }>()

const canvasEl = ref<HTMLCanvasElement | null>(null)
const { start, stop, updateMood, updateAudioData } = useVisual(canvasEl)
const { getFrequencyData } = useAudio()

let audioInterval: ReturnType<typeof setInterval> | null = null

onMounted(() => {
  start(() => emit('ready'))
  updateMood(props.energy, props.valence, props.intensity)
  audioInterval = setInterval(() => updateAudioData(getFrequencyData()), 33)
})

onUnmounted(() => {
  stop()
  if (audioInterval) { clearInterval(audioInterval); audioInterval = null }
})

watch(
  () => [props.energy, props.valence, props.intensity] as const,
  ([e, v, i]) => updateMood(e, v, i),
)
</script>

<template>
  <canvas
    ref="canvasEl"
    class="fixed inset-0 w-full h-full pointer-events-none"
    style="z-index: 0"
  />
</template>
