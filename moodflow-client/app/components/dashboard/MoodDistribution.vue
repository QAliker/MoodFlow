<script setup lang="ts">
import { Doughnut, Bar } from 'vue-chartjs'
import type { TooltipItem } from 'chart.js'

const props = defineProps<{
  sourceBreakdown: { emoji: number; slider: number; activity: number }
  topGenres: string[]
}>()

const total = computed(() =>
  props.sourceBreakdown.emoji + props.sourceBreakdown.slider + props.sourceBreakdown.activity,
)

const doughnutData = computed(() => ({
  labels: ['Emoji', 'Sliders', 'Activité'],
  datasets: [{
    data: [props.sourceBreakdown.emoji, props.sourceBreakdown.slider, props.sourceBreakdown.activity],
    backgroundColor: [
      'rgba(251, 191, 36, 0.85)',
      'rgba(59, 130, 246, 0.85)',
      'rgba(16, 185, 129, 0.85)',
    ],
    borderWidth: 0,
    hoverOffset: 4,
  }],
}))

const barData = computed(() => ({
  labels: props.topGenres,
  datasets: [{
    label: 'Sessions',
    data: props.topGenres.map((_, i) => props.topGenres.length - i),
    backgroundColor: props.topGenres.map((_, i) => `rgba(168, 85, 247, ${0.9 - i * 0.12})`),
    borderRadius: 4,
    borderSkipped: false,
  }],
}))

const doughnutOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: {
      position: 'bottom' as const,
      labels: { color: '#9ca3af', boxWidth: 10, font: { size: 11 }, padding: 12 },
    },
    tooltip: {
      callbacks: {
        label: (ctx: TooltipItem<'doughnut'>) =>
          ` ${ctx.label ?? ''} : ${ctx.parsed} session${ctx.parsed > 1 ? 's' : ''}`,
      },
    },
  },
} as const

const barOptions = {
  responsive: true,
  maintainAspectRatio: false,
  indexAxis: 'y' as const,
  plugins: { legend: { display: false } },
  scales: {
    x: {
      ticks: { color: '#9ca3af', font: { size: 10 } },
      grid: { color: 'rgba(156, 163, 175, 0.1)' },
    },
    y: {
      ticks: { color: '#9ca3af', font: { size: 11 } },
      grid: { display: false },
    },
  },
} as const
</script>

<template>
  <div class="flex flex-col gap-4 h-full">
    <UCard>
      <p class="text-xs text-gray-500 uppercase tracking-wider mb-3">Source des moods</p>
      <div v-if="total === 0" class="h-36 flex items-center justify-center">
        <p class="text-sm text-gray-400">Aucune donnée.</p>
      </div>
      <ClientOnly v-else>
        <div class="h-36">
          <Doughnut :data="doughnutData" :options="doughnutOptions" />
        </div>
        <template #fallback>
          <USkeleton class="h-36 rounded-lg" />
        </template>
      </ClientOnly>
    </UCard>

    <UCard v-if="topGenres.length">
      <p class="text-xs text-gray-500 uppercase tracking-wider mb-3">Top genres</p>
      <ClientOnly>
        <div :style="{ height: `${Math.max(80, topGenres.length * 28)}px` }">
          <Bar :data="barData" :options="barOptions" />
        </div>
        <template #fallback>
          <USkeleton class="h-24 rounded-lg" />
        </template>
      </ClientOnly>
    </UCard>
  </div>
</template>
