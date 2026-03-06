<script setup lang="ts">
const playerStore = usePlayerStore()
const { isPlaying, progress, currentTime, duration, isEnded, play, pause, seek } = useAudio()

// Auto-play quand la track courante change
watch(
  () => playerStore.currentTrack,
  async (track) => {
    if (!track) return
    if (track.preview_url) {
      await play(track.preview_url)
    }
    else {
      // Pas de preview : skip automatiquement
      playerStore.next()
    }
  },
)

// Passage automatique au suivant en fin de morceau
watch(isEnded, (ended) => {
  if (ended && playerStore.hasNext) {
    playerStore.next()
  }
})

async function togglePlay() {
  if (isPlaying.value) {
    pause()
  }
  else if (playerStore.currentTrack?.preview_url) {
    await play()
  }
}

function onProgressClick(e: MouseEvent) {
  const bar = e.currentTarget as HTMLElement
  const pct = (e.offsetX / bar.clientWidth) * 100
  seek(pct)
}

function formatTime(s: number): string {
  if (!s || isNaN(s)) return '0:00'
  return `${Math.floor(s / 60)}:${String(Math.floor(s % 60)).padStart(2, '0')}`
}
</script>

<template>
  <Transition
    enter-active-class="transition duration-300 ease-out"
    enter-from-class="opacity-0 translate-y-4"
    enter-to-class="opacity-100 translate-y-0"
    leave-active-class="transition duration-200 ease-in"
    leave-from-class="opacity-100 translate-y-0"
    leave-to-class="opacity-0 translate-y-4"
  >
    <div
      v-if="playerStore.currentTrack"
      class="fixed bottom-0 left-0 right-0 z-50 border-t border-white/10 bg-gray-950/90 backdrop-blur-xl px-4 py-3"
    >
      <div class="max-w-2xl mx-auto space-y-2">

        <!-- Barre de progression -->
        <div
          class="relative h-1 bg-white/10 rounded-full cursor-pointer group"
          @click="onProgressClick"
        >
          <div
            class="h-full bg-primary-500 rounded-full transition-all duration-100 group-hover:bg-primary-400"
            :style="{ width: `${progress}%` }"
          />
        </div>

        <!-- Contrôles + infos -->
        <div class="flex items-center gap-4">
          <!-- Cover -->
          <img
            :src="playerStore.currentTrack.cover_url"
            :alt="playerStore.currentTrack.album"
            class="w-10 h-10 rounded-lg object-cover flex-shrink-0"
          >

          <!-- Titre + artiste -->
          <div class="flex-1 min-w-0">
            <p class="text-sm font-semibold text-white truncate">
              {{ playerStore.currentTrack.title }}
            </p>
            <p class="text-xs text-gray-400 truncate">
              {{ playerStore.currentTrack.artist }}
            </p>
          </div>

          <!-- Temps -->
          <span class="text-xs text-gray-500 flex-shrink-0 tabular-nums">
            {{ formatTime(currentTime) }} / {{ formatTime(duration) }}
          </span>

          <!-- Boutons de contrôle -->
          <div class="flex items-center gap-1 flex-shrink-0">
            <UButton
              variant="ghost"
              color="neutral"
              size="sm"
              icon="i-heroicons-backward"
              :disabled="!playerStore.hasPrevious"
              @click="playerStore.previous()"
            />
            <UButton
              variant="soft"
              color="primary"
              size="sm"
              :icon="isPlaying ? 'i-heroicons-pause' : 'i-heroicons-play'"
              @click="togglePlay"
            />
            <UButton
              variant="ghost"
              color="neutral"
              size="sm"
              icon="i-heroicons-forward"
              :disabled="!playerStore.hasNext"
              @click="playerStore.next()"
            />
          </div>
        </div>

      </div>
    </div>
  </Transition>
</template>
