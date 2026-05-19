<script setup lang="ts">
import type { MoodProfile } from '~/stores/mood'

const props = defineProps<{ mood: MoodProfile }>()

const playerStore = usePlayerStore()

const EMOJI_MAP: Record<string, string> = {
  'joyeux': '😊', 'triste': '😢', 'en colère': '😡', 'festif': '🥳',
  'serein': '😌', 'intense': '🔥', 'mélancolique': '💔', 'endormi': '😴',
  'agité': '⚡', 'neutre': '😐',
}

function emojiFor(emotion: string): string {
  return EMOJI_MAP[emotion.toLowerCase()] ?? '🎵'
}

const displayTracks = computed(() => props.mood.tracks.slice(0, 3))

const date = computed(() =>
  new Date(props.mood.created_at).toLocaleDateString('fr-FR', {
    day: 'numeric', month: 'long',
  }),
)
</script>

<template>
  <UCard class="bg-gray-900/60 backdrop-blur border border-white/10 h-full">
    <div class="flex flex-col gap-5">
      <!-- Header -->
      <div class="flex items-start justify-between gap-3">
        <div>
          <p class="text-xs text-gray-500 uppercase tracking-wider mb-2">
            Dernière session · {{ date }}
          </p>
          <div class="flex items-center gap-3">
            <span class="text-4xl leading-none">{{ emojiFor(mood.emotion) }}</span>
            <div>
              <p class="text-lg font-bold text-white capitalize">{{ mood.emotion }}</p>
              <div class="flex gap-2 mt-1 flex-wrap">
                <UBadge variant="soft" color="primary" size="xs">
                  Énergie {{ mood.energy }}%
                </UBadge>
                <UBadge variant="soft" color="warning" size="xs">
                  Intensité {{ mood.intensity }}%
                </UBadge>
              </div>
            </div>
          </div>
        </div>
        <UButton
          variant="soft"
          size="sm"
          icon="i-heroicons-play"
          class="shrink-0"
          @click="playerStore.setPlaylist(mood.tracks, 0)"
        >
          Réécouter
        </UButton>
      </div>

      <!-- Tracks -->
      <div class="flex flex-col gap-1.5">
        <button
          v-for="(track, i) in displayTracks"
          :key="track.deezer_id"
          type="button"
          class="flex items-center gap-3 p-2.5 rounded-xl bg-white/5 hover:bg-white/10 transition-colors text-left w-full group"
          @click="playerStore.setPlaylist(mood.tracks, i)"
        >
          <img
            :src="track.cover_url"
            :alt="track.title"
            class="w-10 h-10 rounded-lg object-cover shrink-0"
          />
          <div class="flex-1 min-w-0">
            <p class="text-sm font-medium text-white truncate">{{ track.title }}</p>
            <p class="text-xs text-gray-400 truncate">{{ track.artist }}</p>
          </div>
          <UIcon
            name="i-heroicons-play-circle"
            class="w-5 h-5 text-primary-400 shrink-0 opacity-0 group-hover:opacity-100 transition-opacity"
          />
        </button>

        <p v-if="mood.tracks.length > 3" class="text-xs text-gray-600 text-center pt-1">
          + {{ mood.tracks.length - 3 }} autres tracks
        </p>
      </div>
    </div>
  </UCard>
</template>
