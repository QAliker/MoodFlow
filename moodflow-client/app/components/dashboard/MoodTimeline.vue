<script setup lang="ts">
import type { TimelineEntry } from '~/stores/dashboard'

const props = defineProps<{
  timeline: TimelineEntry[]
  hasMore: boolean
  loading: boolean
}>()

const emit = defineEmits<{ loadMore: [] }>()

const sentinel = ref<HTMLElement | null>(null)
const expanded = ref<Record<number, boolean>>({})

const EMOJI_MAP: Record<string, string> = {
  'joyeux': '😊', 'triste': '😢', 'en colère': '😡', 'festif': '🥳',
  'serein': '😌', 'intense': '🔥', 'mélancolique': '💔', 'endormi': '😴',
  'agité': '⚡', 'neutre': '😐',
}

function emojiFor(emotion: string): string {
  return EMOJI_MAP[emotion.toLowerCase()] ?? '🎵'
}

function dotColor(entry: TimelineEntry): string {
  if (entry.energy > 70) return 'bg-orange-500'
  if (entry.energy > 40) return 'bg-blue-500'
  return 'bg-purple-500'
}

function formatDate(dateStr: string, time: string): string {
  const d = new Date(dateStr)
  const diffMs = Date.now() - d.getTime()
  let label: string
  if (diffMs < 86_400_000) label = "Aujourd'hui"
  else if (diffMs < 172_800_000) label = 'Hier'
  else label = d.toLocaleDateString('fr-FR', { day: 'numeric', month: 'short' })
  return `${label} · ${time}`
}

function toggleExpand(id: number) {
  expanded.value[id] = !expanded.value[id]
}

const moodStore = useMoodStore()
const router = useRouter()

function replay(entry: TimelineEntry) {
  moodStore.setForm({
    source: entry.source as 'emoji' | 'slider',
    emotion: entry.emotion,
    energy: entry.energy,
    valence: entry.valence,
    intensity: entry.intensity,
  })
  if (entry.activity_mode) {
    moodStore.setActivityMode(entry.activity_mode)
  }
  else {
    moodStore.clearActivityMode()
  }
  router.push('/mood')
}

let observer: IntersectionObserver | null = null

onMounted(() => {
  if (!sentinel.value) return
  observer = new IntersectionObserver(
    (entries) => {
      if (entries[0]?.isIntersecting && props.hasMore && !props.loading) {
        emit('loadMore')
      }
    },
    { rootMargin: '200px' },
  )
  observer.observe(sentinel.value)
})

onUnmounted(() => observer?.disconnect())
</script>

<template>
  <div class="flex flex-col gap-3">
    <div class="flex items-center justify-between">
      <p class="text-xs text-gray-500 uppercase tracking-wider">Historique complet</p>
      <p class="text-xs text-gray-500">{{ timeline.length }} session{{ timeline.length > 1 ? 's' : '' }}</p>
    </div>

    <div v-if="timeline.length" class="relative">
      <div class="absolute left-4 top-5 bottom-0 w-px bg-gray-200 dark:bg-gray-800" />

      <div
        v-for="entry in timeline"
        :key="entry.id"
        class="relative flex gap-4"
      >
        <div class="relative z-10 flex shrink-0 pt-3.5">
          <div
            class="w-3 h-3 rounded-full border-2 border-white dark:border-gray-950 ml-2.5"
            :class="dotColor(entry)"
          />
        </div>

        <div class="flex-1 pb-3 min-w-0">
          <button type="button" class="w-full text-left" @click="toggleExpand(entry.id)">
            <UCard class="transition-colors hover:border-gray-300 dark:hover:border-gray-700">
              <div class="flex items-start justify-between gap-3">
                <div class="flex items-center gap-3 min-w-0">
                  <span class="text-2xl leading-none shrink-0">{{ emojiFor(entry.emotion) }}</span>
                  <div class="min-w-0">
                    <p class="text-sm font-semibold text-gray-900 dark:text-white capitalize">
                      {{ entry.emotion }}
                    </p>
                    <p class="text-xs text-gray-500">{{ formatDate(entry.date, entry.time) }}</p>
                  </div>
                </div>

                <div class="flex items-center gap-1.5 shrink-0">
                  <UBadge variant="soft" color="primary" size="xs">E {{ entry.energy }}%</UBadge>
                  <UBadge variant="soft" color="neutral" size="xs">I {{ entry.intensity }}%</UBadge>
                  <UIcon
                    :name="expanded[entry.id] ? 'i-heroicons-chevron-up' : 'i-heroicons-chevron-down'"
                    class="w-4 h-4 text-gray-400 ml-1"
                  />
                </div>
              </div>

              <Transition
                enter-active-class="transition duration-150 ease-out"
                enter-from-class="opacity-0 -translate-y-1"
                enter-to-class="opacity-100 translate-y-0"
                leave-active-class="transition duration-100 ease-in"
                leave-from-class="opacity-100 translate-y-0"
                leave-to-class="opacity-0 -translate-y-1"
              >
                <div
                  v-if="expanded[entry.id]"
                  class="mt-3 pt-3 border-t border-gray-100 dark:border-gray-800 flex flex-col gap-3"
                >
                  <div class="flex flex-wrap gap-1.5">
                    <UBadge variant="soft" size="xs" color="neutral">via {{ entry.source }}</UBadge>
                    <UBadge v-if="entry.activity_mode" variant="soft" size="xs" color="primary">
                      {{ entry.activity_mode }}
                    </UBadge>
                    <UBadge v-if="entry.weather" variant="soft" size="xs" color="neutral">
                      {{ entry.weather }}
                    </UBadge>
                  </div>

                  <div v-if="entry.tracks.length" class="flex flex-col gap-1">
                    <div
                      v-for="(track, i) in entry.tracks"
                      :key="i"
                      class="flex items-center gap-2 px-2.5 py-1.5 rounded-lg bg-gray-50 dark:bg-gray-800/50"
                    >
                      <UIcon name="i-heroicons-musical-note" class="w-3 h-3 text-gray-400 shrink-0" />
                      <span class="text-xs text-gray-700 dark:text-gray-300 truncate">
                        {{ track.title }} — {{ track.artist }}
                      </span>
                    </div>
                  </div>

                  <UButton
                    size="sm"
                    variant="soft"
                    color="primary"
                    icon="i-heroicons-play"
                    class="self-start"
                    @click.stop="replay(entry)"
                  >
                    Rejouer ce mood
                  </UButton>
                </div>
              </Transition>
            </UCard>
          </button>
        </div>
      </div>

      <div ref="sentinel" class="h-1" />

      <div v-if="loading" class="flex justify-center py-4">
        <UIcon name="i-heroicons-arrow-path" class="w-5 h-5 text-gray-400 animate-spin" />
      </div>

      <div v-if="hasMore && !loading" class="flex justify-center pt-2">
        <UButton variant="ghost" color="neutral" size="sm" @click="$emit('loadMore')">
          Charger plus
        </UButton>
      </div>
    </div>

    <div v-else-if="!loading" class="flex flex-col items-center gap-3 py-12 text-center">
      <span class="text-4xl">📭</span>
      <p class="text-sm text-gray-500">Aucune session encore enregistrée.</p>
    </div>
  </div>
</template>
