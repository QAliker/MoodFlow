<script setup lang="ts">
import type { Genre } from '~/stores/auth'

const props = defineProps<{
  genres: Genre[]
  modelValue: number[]
}>()

const emit = defineEmits<{
  'update:modelValue': [value: number[]]
}>()

const MIN = 1
const MAX = 3

function toggle(id: number): void {
  const selected = [...props.modelValue]
  const index = selected.indexOf(id)
  if (index !== -1) {
    selected.splice(index, 1)
  } else if (selected.length < MAX) {
    selected.push(id)
  }
  emit('update:modelValue', selected)
}

const count = computed(() => props.modelValue.length)
const remaining = computed(() => MIN - count.value)
const isValid = computed(() => count.value >= MIN && count.value <= MAX)
</script>

<template>
  <div class="space-y-6">
    <!-- Progress -->
    <div class="space-y-2">
      <div class="flex justify-between items-center text-sm">
        <span class="text-gray-400">
          <template v-if="count === 0">Commence ta selection</template>
          <template v-else-if="remaining > 0">Encore {{ remaining }} genre{{ remaining > 1 ? 's' : '' }} minimum</template>
          <template v-else-if="count === MAX">Maximum atteint !</template>
          <template v-else>Bonne selection ! Tu peux en ajouter {{ MAX - count }} de plus</template>
        </span>
        <span
          class="font-semibold tabular-nums transition-colors duration-300"
          :class="isValid ? 'text-primary-400' : 'text-gray-500'"
        >{{ count }}/{{ MAX }}</span>
      </div>
      <div class="h-1.5 bg-white/10 rounded-full overflow-hidden">
        <div
          class="h-full rounded-full transition-all duration-500"
          :class="isValid ? 'bg-primary-500' : 'bg-gray-500'"
          :style="{ width: `${(count / MAX) * 100}%` }"
        />
      </div>
      <div class="flex gap-1.5 justify-center pt-0.5">
        <div
          v-for="i in MAX"
          :key="i"
          class="h-1 rounded-full transition-all duration-300"
          :class="[
            i <= count ? 'bg-primary-500' : 'bg-white/10',
            i <= count ? 'w-6' : 'w-3',
          ]"
        />
      </div>
    </div>

    <!-- Grid -->
    <div class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-5 gap-3">
      <button
        v-for="genre in genres"
        :key="genre.id"
        type="button"
        :disabled="count >= MAX && !modelValue.includes(genre.id)"
        :aria-pressed="modelValue.includes(genre.id)"
        class="group relative flex flex-col items-center justify-center gap-2 rounded-2xl p-4 transition-all duration-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2 focus-visible:ring-offset-gray-900 select-none"
        :class="[
          modelValue.includes(genre.id)
            ? 'bg-primary-500 shadow-lg shadow-primary-500/30 scale-105 text-white'
            : count >= MAX
              ? 'bg-white/5 text-gray-600 opacity-40 cursor-not-allowed'
              : 'bg-white/5 hover:bg-white/10 text-gray-300 hover:text-white shadow-sm hover:shadow-md hover:scale-105 cursor-pointer border border-white/5 hover:border-white/15',
        ]"
        @click="toggle(genre.id)"
      >
        <span class="text-3xl leading-none transition-transform duration-200 group-hover:scale-110 group-active:scale-90">
          {{ genre.icon }}
        </span>
        <span class="text-xs font-medium leading-tight text-center">{{ genre.name }}</span>

        <Transition
          enter-active-class="transition duration-150 ease-out"
          enter-from-class="opacity-0 scale-50"
          enter-to-class="opacity-100 scale-100"
          leave-active-class="transition duration-100 ease-in"
          leave-from-class="opacity-100 scale-100"
          leave-to-class="opacity-0 scale-50"
        >
          <span
            v-if="modelValue.includes(genre.id)"
            class="absolute top-2 right-2 w-4 h-4 bg-white/20 rounded-full flex items-center justify-center"
          >
            <UIcon name="i-heroicons-check-20-solid" class="w-3 h-3 text-white" />
          </span>
        </Transition>
      </button>
    </div>
  </div>
</template>
