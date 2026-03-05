<script setup lang="ts">
interface Genre {
  id: number
  name: string
  slug: string
  icon: string
  deezer_id: number | null
}

const props = defineProps<{
  genres: Genre[]
  modelValue: number[]
}>()

const emit = defineEmits<{
  'update:modelValue': [value: number[]]
}>()

const MIN_GENRES = 3
const MAX_GENRES = 5

function toggle(id: number): void {
  const selected = [...props.modelValue]
  const index = selected.indexOf(id)

  if (index !== -1) {
    selected.splice(index, 1)
  } else if (selected.length < MAX_GENRES) {
    selected.push(id)
  }

  emit('update:modelValue', selected)
}

function isSelected(id: number): boolean {
  return props.modelValue.includes(id)
}

function isDisabled(id: number): boolean {
  return props.modelValue.length >= MAX_GENRES && !isSelected(id)
}

const count = computed(() => props.modelValue.length)
const tooFew = computed(() => count.value < MIN_GENRES)
const atMax = computed(() => count.value >= MAX_GENRES)
</script>

<template>
  <div>
    <div class="flex items-center justify-between mb-4">
      <p class="text-sm text-gray-500 dark:text-gray-400">
        Sélectionne entre {{ MIN_GENRES }} et {{ MAX_GENRES }} genres
      </p>
      <UBadge
        :color="tooFew ? 'warning' : atMax ? 'success' : 'primary'"
        variant="soft"
      >
        {{ count }} / {{ MAX_GENRES }}
      </UBadge>
    </div>

    <div class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-5 gap-3">
      <button
        v-for="genre in genres"
        :key="genre.id"
        type="button"
        :disabled="isDisabled(genre.id)"
        :aria-pressed="isSelected(genre.id)"
        class="relative flex flex-col items-center justify-center gap-2 p-4 rounded-xl border-2 transition-all duration-150 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
        :class="[
          isSelected(genre.id)
            ? 'border-primary-500 bg-primary-50 dark:bg-primary-950 text-primary-700 dark:text-primary-300 shadow-md scale-105'
            : isDisabled(genre.id)
              ? 'border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 text-gray-400 dark:text-gray-600 opacity-50 cursor-not-allowed'
              : 'border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 hover:border-primary-300 hover:bg-primary-50/50 dark:hover:bg-primary-950/30 cursor-pointer',
        ]"
        @click="toggle(genre.id)"
      >
        <span class="text-2xl leading-none select-none">{{ genre.icon }}</span>
        <span class="text-xs font-medium text-center leading-tight">{{ genre.name }}</span>

        <span
          v-if="isSelected(genre.id)"
          class="absolute top-1.5 right-1.5 w-4 h-4 rounded-full bg-primary-500 flex items-center justify-center"
        >
          <UIcon name="i-heroicons-check" class="w-2.5 h-2.5 text-white" />
        </span>
      </button>
    </div>

    <p v-if="tooFew && count > 0" class="mt-3 text-xs text-warning-600 dark:text-warning-400">
      Encore {{ MIN_GENRES - count }} genre{{ MIN_GENRES - count > 1 ? 's' : '' }} a selectionner
    </p>
    <p v-else-if="atMax" class="mt-3 text-xs text-success-600 dark:text-success-400">
      Selection complete ! Tu peux valider.
    </p>
  </div>
</template>
