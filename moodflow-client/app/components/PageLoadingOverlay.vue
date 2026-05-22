<!-- moodflow-client/app/components/PageLoadingOverlay.vue -->
<script setup lang="ts">
defineProps<{ show: boolean }>()
</script>

<template>
  <Teleport to="body">
    <Transition
      enter-active-class="transition duration-300 ease-out"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="transition duration-500 ease-in"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div
        v-if="show"
        class="fixed inset-0 z-50 flex items-center justify-center bg-gray-950/75 backdrop-blur-sm"
        aria-live="polite"
        aria-label="Chargement en cours"
      >
        <div class="relative flex items-center justify-center">
          <span class="absolute inline-flex h-32 w-32 rounded-full bg-primary-500/10 animate-ping" style="animation-duration: 2.4s" />
          <span class="absolute inline-flex h-20 w-20 rounded-full bg-primary-500/15 animate-ping" style="animation-duration: 1.8s; animation-delay: 0.4s" />
          <span class="absolute inline-flex h-12 w-12 rounded-full bg-primary-500/20 animate-ping" style="animation-duration: 1.2s; animation-delay: 0.8s" />
          <div class="relative z-10 flex flex-col items-center gap-3">
            <UIcon name="i-heroicons-musical-note" class="w-8 h-8 text-primary-400 animate-pulse" />
            <span class="text-sm font-medium text-gray-300">Chargement…</span>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>
