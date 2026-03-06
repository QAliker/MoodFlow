import { defineStore } from 'pinia'
import type { Track } from './mood'

export const usePlayerStore = defineStore('player', () => {
  const playlist = ref<Track[]>([])
  const currentIndex = ref(-1)

  const currentTrack = computed<Track | null>(() =>
    currentIndex.value >= 0 ? (playlist.value[currentIndex.value] ?? null) : null,
  )
  const hasPrevious = computed(() => currentIndex.value > 0)
  const hasNext = computed(() => currentIndex.value < playlist.value.length - 1)

  function setPlaylist(tracks: Track[], startIndex = -1) {
    playlist.value = tracks
    currentIndex.value = startIndex
  }

  function playAt(index: number) {
    if (index >= 0 && index < playlist.value.length) {
      currentIndex.value = index
    }
  }

  function next() {
    if (hasNext.value) currentIndex.value++
  }

  function previous() {
    if (hasPrevious.value) currentIndex.value--
  }

  return { playlist, currentIndex, currentTrack, hasPrevious, hasNext, setPlaylist, playAt, next, previous }
})
