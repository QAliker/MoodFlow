<script setup lang="ts">
definePageMeta({ middleware: 'auth' })

const dashboardStore = useDashboardStore()

const period = ref<'week' | 'month' | 'all'>('month')

const periodOptions = [
  { label: 'Semaine', value: 'week' as const },
  { label: 'Mois', value: 'month' as const },
  { label: 'Tout', value: 'all' as const },
]

function setPeriod(val: 'week' | 'month' | 'all') {
  period.value = val
  dashboardStore.fetchStats(val)
}

onMounted(() => {
  dashboardStore.fetchStats(period.value)
  dashboardStore.fetchTimeline(1)
})
</script>

<template>
  <UContainer class="py-8 max-w-6xl">
    <div class="flex flex-col gap-6">
      <div class="flex items-center justify-between gap-4 flex-wrap">
        <div>
          <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Dashboard</h1>
          <p class="text-sm text-gray-500 mt-0.5">Ton évolution émotionnelle</p>
        </div>

        <div class="flex rounded-lg border border-gray-200 dark:border-gray-800 overflow-hidden">
          <UButton
            v-for="opt in periodOptions"
            :key="opt.value"
            :variant="period === opt.value ? 'solid' : 'ghost'"
            color="primary"
            size="sm"
            class="rounded-none"
            @click="setPeriod(opt.value)"
          >
            {{ opt.label }}
          </UButton>
        </div>
      </div>

      <DashboardMoodStats v-if="dashboardStore.stats" :stats="dashboardStore.stats" />
      <div v-else class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <USkeleton v-for="i in 4" :key="i" class="h-28 rounded-xl" />
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-5 gap-6">
        <div class="lg:col-span-3">
          <DashboardMoodChart
            v-if="dashboardStore.stats"
            :weekly-evolution="dashboardStore.stats.weekly_evolution"
          />
        </div>
        <div class="lg:col-span-2">
          <DashboardMoodDistribution
            v-if="dashboardStore.stats"
            :source-breakdown="dashboardStore.stats.source_breakdown"
            :top-genres="dashboardStore.stats.top_genres"
          />
        </div>
      </div>

      <DashboardMoodTimeline
        :timeline="dashboardStore.timeline"
        :has-more="dashboardStore.hasMore"
        :loading="dashboardStore.loadingTimeline"
        @load-more="dashboardStore.loadMore()"
      />
    </div>
  </UContainer>
</template>
