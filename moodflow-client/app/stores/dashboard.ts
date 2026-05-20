import { defineStore } from 'pinia'

export interface DashboardStats {
  averages: { energy: number; valence: number; intensity: number }
  most_frequent_emotion: string | null
  most_used_activity_mode: string | null
  most_active_day: string | null
  preferred_hour: number | null
  total_sessions: number
  weekly_evolution: Array<{
    week: string
    energy: number
    valence: number
    intensity: number
    count: number
  }>
  top_genres: string[]
  source_breakdown: { emoji: number; slider: number; activity: number }
}

export interface TimelineEntry {
  id: number
  date: string
  time: string
  source: string
  emotion: string
  energy: number
  valence: number
  intensity: number
  activity_mode: string | null
  weather: string | null
  tracks: Array<{ title: string; artist: string }>
}

export const useDashboardStore = defineStore('dashboard', () => {
  const apiFetch = useApiFetch()

  const stats = ref<DashboardStats | null>(null)
  const timeline = ref<TimelineEntry[]>([])
  const currentPage = ref(1)
  const lastPage = ref(1)
  const loadingStats = ref(false)
  const loadingTimeline = ref(false)

  const hasMore = computed(() => currentPage.value < lastPage.value)

  async function fetchStats(period = 'month') {
    loadingStats.value = true
    try {
      const res = await apiFetch<{ data: DashboardStats }>(`/api/mood/stats?period=${period}`)
      stats.value = res.data
    }
    finally {
      loadingStats.value = false
    }
  }

  async function fetchTimeline(page = 1) {
    if (loadingTimeline.value) return
    loadingTimeline.value = true
    try {
      const res = await apiFetch<{
        data: { data: TimelineEntry[]; current_page: number; last_page: number }
      }>(`/api/mood/history?page=${page}`)
      if (page === 1) {
        timeline.value = res.data.data
      }
      else {
        timeline.value.push(...res.data.data)
      }
      currentPage.value = res.data.current_page
      lastPage.value = res.data.last_page
    }
    finally {
      loadingTimeline.value = false
    }
  }

  async function loadMore() {
    if (hasMore.value) {
      await fetchTimeline(currentPage.value + 1)
    }
  }

  return {
    stats,
    timeline,
    loadingStats,
    loadingTimeline,
    hasMore,
    fetchStats,
    fetchTimeline,
    loadMore,
  }
})
