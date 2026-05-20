<script setup lang="ts">
import type { DashboardStats } from '~/stores/dashboard'

const props = defineProps<{ stats: DashboardStats }>()

const EMOJI_MAP: Record<string, string> = {
  'joyeux': '😊', 'triste': '😢', 'en colère': '😡', 'festif': '🥳',
  'serein': '😌', 'intense': '🔥', 'mélancolique': '💔', 'endormi': '😴',
  'agité': '⚡', 'neutre': '😐',
}

const cards = computed(() => {
  const emotion = props.stats.most_frequent_emotion
  const emoji = emotion ? (EMOJI_MAP[emotion.toLowerCase()] ?? '🎵') : '—'

  return [
    {
      label: 'Sessions',
      value: String(props.stats.total_sessions),
      sub: props.stats.most_active_day ? `Jour actif : ${props.stats.most_active_day}` : 'Aucune donnée',
      icon: 'i-heroicons-chart-bar',
      color: 'text-primary-500',
    },
    {
      label: 'Mood dominant',
      value: emotion ? `${emoji} ${emotion}` : '—',
      sub: `Énergie moy. ${props.stats.averages.energy}%`,
      icon: 'i-heroicons-face-smile',
      color: 'text-amber-500',
    },
    {
      label: 'Mode préféré',
      value: props.stats.most_used_activity_mode ?? '—',
      sub: props.stats.most_used_activity_mode ? 'Mode le plus utilisé' : 'Aucun mode utilisé',
      icon: 'i-heroicons-bolt',
      color: 'text-emerald-500',
    },
    {
      label: 'Genre top',
      value: props.stats.top_genres[0] ?? '—',
      sub: props.stats.top_genres.length > 1 ? `+ ${props.stats.top_genres.length - 1} autres` : 'Genre unique',
      icon: 'i-heroicons-tag',
      color: 'text-purple-500',
    },
  ]
})
</script>

<template>
  <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
    <UCard v-for="card in cards" :key="card.label">
      <div class="flex flex-col gap-2">
        <div class="flex items-center justify-between">
          <p class="text-xs text-gray-500 uppercase tracking-wider">{{ card.label }}</p>
          <UIcon :name="card.icon" class="w-4 h-4" :class="card.color" />
        </div>
        <p class="text-lg font-bold text-gray-900 dark:text-white capitalize truncate">
          {{ card.value }}
        </p>
        <p class="text-xs text-gray-500 capitalize">{{ card.sub }}</p>
      </div>
    </UCard>
  </div>
</template>
