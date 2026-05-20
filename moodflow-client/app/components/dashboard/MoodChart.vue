<script setup lang="ts">
import { Line } from 'vue-chartjs'
import type { TooltipItem } from 'chart.js'

interface WeeklyPoint {
  week: string
  energy: number
  valence: number
  intensity: number
  count: number
}

const props = defineProps<{ weeklyEvolution: WeeklyPoint[] }>()

function formatWeek(dateStr: string): string {
  return new Date(dateStr).toLocaleDateString('fr-FR', { day: 'numeric', month: 'short' })
}

const chartData = computed(() => ({
  labels: props.weeklyEvolution.map(w => formatWeek(w.week)),
  datasets: [
    {
      label: 'Énergie',
      data: props.weeklyEvolution.map(w => w.energy),
      borderColor: 'rgb(239, 68, 68)',
      backgroundColor: 'rgba(239, 68, 68, 0.08)',
      tension: 0.4,
      fill: true,
      pointRadius: 4,
      pointHoverRadius: 6,
    },
    {
      label: 'Valence',
      data: props.weeklyEvolution.map(w => ((w.valence + 1) / 2) * 100),
      borderColor: 'rgb(59, 130, 246)',
      backgroundColor: 'rgba(59, 130, 246, 0.08)',
      tension: 0.4,
      fill: true,
      pointRadius: 4,
      pointHoverRadius: 6,
    },
    {
      label: 'Intensité',
      data: props.weeklyEvolution.map(w => w.intensity),
      borderColor: 'rgb(168, 85, 247)',
      backgroundColor: 'rgba(168, 85, 247, 0.08)',
      tension: 0.4,
      fill: true,
      pointRadius: 4,
      pointHoverRadius: 6,
    },
  ],
}))

const chartOptions = {
  responsive: true,
  maintainAspectRatio: false,
  interaction: { mode: 'index' as const, intersect: false },
  plugins: {
    legend: {
      labels: { color: '#9ca3af', boxWidth: 10, font: { size: 11 } },
    },
    tooltip: {
      callbacks: {
        label: (ctx: TooltipItem<'line'>) =>
          ` ${ctx.dataset.label ?? ''} : ${Math.round(ctx.parsed.y ?? 0)}%`,
      },
    },
  },
  scales: {
    x: {
      ticks: { color: '#9ca3af', font: { size: 11 } },
      grid: { color: 'rgba(156, 163, 175, 0.1)' },
    },
    y: {
      min: 0,
      max: 100,
      ticks: { color: '#9ca3af', font: { size: 11 }, callback: (v: unknown) => `${v}%` },
      grid: { color: 'rgba(156, 163, 175, 0.1)' },
    },
  },
} as const
</script>

<template>
  <UCard class="h-full">
    <div class="flex flex-col gap-3">
      <p class="text-xs text-gray-500 uppercase tracking-wider">Évolution émotionnelle</p>

      <div v-if="!weeklyEvolution.length" class="h-52 flex items-center justify-center">
        <p class="text-sm text-gray-400">Pas encore de données sur cette période.</p>
      </div>

      <ClientOnly v-else>
        <div class="h-52">
          <Line :data="chartData" :options="chartOptions" />
        </div>
        <template #fallback>
          <USkeleton class="h-52 rounded-lg" />
        </template>
      </ClientOnly>
    </div>
  </UCard>
</template>
