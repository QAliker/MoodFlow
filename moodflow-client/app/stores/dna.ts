// moodflow-client/app/stores/dna.ts
import { defineStore } from 'pinia'

export interface MoodDna {
  dominantEmotion: string | null
  energyProfile: 'calm' | 'moderate' | 'energetic'
  valenceProfile: 'melancholic' | 'neutral' | 'positive'
  consistency: number
  peakHour: number | null
  genreFingerprint: string[]
  activityProfile: string[]
  shape: number
  colors: [string, string, string, string]
  pulseSpeed: number
  symmetry: number
}

export interface InsufficientData {
  status: 'insufficient_data'
  minimum: number
  current: number
}

export const useDnaStore = defineStore('dna', () => {
  const apiFetch = useApiFetch()

  const dna = ref<MoodDna | null>(null)
  const insufficientData = ref<InsufficientData | null>(null)
  const loading = ref(false)
  const error = ref<string | null>(null)

  const isInsufficient = computed(() => insufficientData.value !== null)
  const remaining = computed(() =>
    insufficientData.value
      ? insufficientData.value.minimum - insufficientData.value.current
      : 0,
  )

  async function fetchDna(): Promise<void> {
    loading.value = true
    error.value = null
    try {
      const res = await apiFetch<{ data: MoodDna | InsufficientData }>('/api/mood/dna')
      if ('status' in res.data && res.data.status === 'insufficient_data') {
        insufficientData.value = res.data as InsufficientData
        dna.value = null
      }
      else {
        dna.value = res.data as MoodDna
        insufficientData.value = null
      }
    }
    catch (e: unknown) {
      error.value = e instanceof Error ? e.message : 'Une erreur est survenue.'
    }
    finally {
      loading.value = false
    }
  }

  return { dna, insufficientData, loading, error, isInsufficient, remaining, fetchDna }
})
