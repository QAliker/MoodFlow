<script setup lang="ts">
import type { Track } from '~/stores/mood'

defineProps<{ tracks: Track[] }>()

const playerStore = usePlayerStore()
const { isPlaying } = useAudio()
</script>

<template>
  <UCard class="bg-gray-900/60 backdrop-blur border border-white/10">
    <div class="space-y-1">
      <button
        v-for="(track, index) in tracks"
        :key="track.deezer_id"
        type="button"
        class="w-full flex items-center gap-3 p-3 rounded-xl transition-all duration-150 group text-left"
        :class="playerStore.currentIndex === index
          ? 'bg-primary-500/15 border border-primary-500/30'
          : 'hover:bg-white/5 border border-transparent'"
        @click="playerStore.playAt(index)"
      >
        <!-- Cover + indicateur de lecture -->
        <div class="relative flex-shrink-0">
          <img
            :src="track.cover_url"
            :alt="track.album"
            class="w-10 h-10 rounded-lg object-cover"
          >
          <div
            v-if="playerStore.currentIndex === index"
            class="absolute inset-0 flex items-center justify-center rounded-lg bg-black/50"
          >
            <UIcon
              :name="isPlaying ? 'i-heroicons-speaker-wave' : 'i-heroicons-pause'"
              class="w-4 h-4 text-primary-400"
            />
          </div>
        </div>

        <!-- Info -->
        <div class="flex-1 min-w-0">
          <p
            class="text-sm font-semibold truncate transition-colors"
            :class="playerStore.currentIndex === index ? 'text-primary-300' : 'text-white group-hover:text-white'"
          >
            {{ track.title }}
          </p>
          <p class="text-xs text-gray-400 truncate">{{ track.artist }}</p>
        </div>

        <!-- Durée -->
        <span v-if="track.duration" class="text-xs text-gray-500 flex-shrink-0">
          {{ Math.floor(track.duration / 60) }}:{{ String(track.duration % 60).padStart(2, '0') }}
        </span>
      </button>
    </div>
  </UCard>
</template>
