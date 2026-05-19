<script setup lang="ts">
import type { MoodProfile } from '~/stores/mood'

defineProps<{ history: MoodProfile[] }>()

const playerStore = usePlayerStore()

const EMOJI_MAP: Record<string, string> = {
  'joyeux': '😊', 'triste': '😢', 'en colère': '😡', 'festif': '🥳',
  'serein': '😌', 'intense': '🔥', 'mélancolique': '💔', 'endormi': '😴',
  'agité': '⚡', 'neutre': '😐',
}

function emojiFor(emotion: string): string {
  return EMOJI_MAP[emotion.toLowerCase()] ?? '🎵'
}

function formatDate(dateStr: string): string {
  const d = new Date(dateStr)
  const diffMs = Date.now() - d.getTime()
  if (diffMs < 86_400_000) return "Aujourd'hui"
  if (diffMs < 172_800_000) return 'Hier'
  return d.toLocaleDateString('fr-FR', { day: 'numeric', month: 'short' })
}
</script>

<template>
  <UCard class="bg-gray-900/60 backdrop-blur border border-white/10 h-full">
    <div class="flex flex-col gap-3">
      <p class="text-xs text-gray-500 uppercase tracking-wider">Historique</p>

      <div class="flex flex-col gap-0.5">
        <button
          v-for="mood in history"
          :key="mood.id"
          type="button"
          class="flex items-center gap-3 px-3 py-2.5 rounded-xl hover:bg-white/5 transition-colors text-left w-full group"
          @click="playerStore.setPlaylist(mood.tracks, 0)"
        >
          <span class="text-2xl w-8 text-center leading-none shrink-0">
            {{ emojiFor(mood.emotion) }}
          </span>
          <div class="flex-1 min-w-0">
            <p class="text-sm font-medium text-white capitalize truncate">{{ mood.emotion }}</p>
            <p class="text-xs text-gray-500">
              {{ mood.tracks.length }} tracks · énergie {{ mood.energy }}%
            </p>
          </div>
          <div class="flex items-center gap-2 shrink-0">
            <span class="text-xs text-gray-600">{{ formatDate(mood.created_at) }}</span>
            <UIcon
              name="i-heroicons-play-circle"
              class="w-4 h-4 text-primary-400 opacity-0 group-hover:opacity-100 transition-opacity"
            />
          </div>
        </button>
      </div>
    </div>
  </UCard>
</template>
