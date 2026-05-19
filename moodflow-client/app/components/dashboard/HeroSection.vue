<script setup lang="ts">
defineProps<{
  userName: string
  hasHistory: boolean
}>()
</script>

<template>
  <div class="relative rounded-3xl overflow-hidden bg-gray-900/60 backdrop-blur border border-white/10 px-8 py-12 text-center">
    <div class="absolute inset-0 pointer-events-none" aria-hidden="true">
      <div class="absolute -top-20 -right-20 w-64 h-64 bg-primary-500/10 rounded-full blur-3xl" />
      <div class="absolute -bottom-20 -left-20 w-64 h-64 bg-primary-700/10 rounded-full blur-3xl" />
    </div>

    <div class="relative z-10 flex flex-col items-center gap-4">
      <span class="text-5xl leading-none select-none">🎵</span>
      <h1 class="text-3xl md:text-4xl font-bold text-white">
        Comment tu te sens
        <span class="text-primary-400"> aujourd'hui</span>&nbsp;?
      </h1>
      <p class="text-gray-400 max-w-md text-sm leading-relaxed">
        {{ hasHistory
          ? 'Lance une nouvelle session ou réécoute tes dernières playlists.'
          : 'Crée ta première playlist basée sur ton humeur du moment.' }}
      </p>
      <NuxtLink to="/mood" class="mt-2">
        <UButton
          size="xl"
          trailing-icon="i-heroicons-arrow-right-20-solid"
          class="shadow-lg shadow-primary-500/30"
        >
          Nouveau mood
        </UButton>
      </NuxtLink>
    </div>
  </div>
</template>
