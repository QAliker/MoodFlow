import { defineStore } from 'pinia'

export interface Track {
  deezer_id: number
  title: string
  artist: string
  album: string
  preview_url: string
  cover_url: string
  duration: number
}

export interface MoodProfile {
  id: number
  source: string
  emotion: string
  energy: number
  valence: number
  intensity: number
  context: Record<string, unknown>
  tracks: Track[]
  created_at: string
}

export interface MoodForm {
  source: 'emoji' | 'slider'
  emotion: string | null
  energy: number
  valence: number   // -1 to 1
  intensity: number
}

export interface MoodPrediction {
  predicted_mood: { energy: number; valence: number; intensity: number }
  confidence: number
  based_on: number
  suggested_activity: string | null
  message: string
}

interface AnalyzePayload {
  source: string
  emotion?: string | null
  energy: number
  valence: number
  intensity: number
  lat?: number | null
  lon?: number | null
  activity_mode?: string | null
}

export const useMoodStore = defineStore('mood', () => {
  const apiFetch = useApiFetch()

  const form = ref<MoodForm>({
    source: 'emoji',
    emotion: null,
    energy: 50,
    valence: 0,
    intensity: 50,
  })

  const moodProfile = ref<MoodProfile | null>(null)
  const tracks = ref<Track[]>([])
  const history = ref<MoodProfile[]>([])
  const loading = ref(false)
  const error = ref<string | null>(null)
  const activityMode = ref<string | null>(null)
  const suggestedMode = ref<string | null>(null)
  const prediction = ref<MoodPrediction | null>(null)
  const predictionLoading = ref(false)

  function setForm(values: Partial<MoodForm>) {
    form.value = { ...form.value, ...values }
  }

  function setActivityMode(slug: string, defaults?: { energy: number; valence: number; intensity: number }) {
    activityMode.value = slug
    if (defaults) {
      form.value = { ...form.value, energy: defaults.energy, valence: defaults.valence, intensity: defaults.intensity }
    }
  }

  function clearActivityMode() {
    activityMode.value = null
  }

  async function fetchPrediction(coords?: { lat: number; lon: number } | null): Promise<void> {
    predictionLoading.value = true
    try {
      const params = coords ? `?lat=${coords.lat}&lon=${coords.lon}` : ''
      const response = await apiFetch<MoodPrediction>(`/api/mood/predict${params}`)
      prediction.value = response
    }
    catch {
      // Prediction is non-critical — fail silently
    }
    finally {
      predictionLoading.value = false
    }
  }

  async function analyze(coords?: { lat: number; lon: number } | null): Promise<void> {
    loading.value = true
    error.value = null
    try {
      const payload: AnalyzePayload = {
        source:        form.value.source,
        emotion:       form.value.emotion,
        energy:        form.value.energy,
        valence:       form.value.valence,
        intensity:     form.value.intensity,
        lat:           coords?.lat ?? null,
        lon:           coords?.lon ?? null,
        activity_mode: activityMode.value,
      }

      const response = await apiFetch<{ data: { mood: MoodProfile; tracks: Track[]; suggested_mode: string | null } }>(
        '/api/mood/analyze',
        { method: 'POST', body: payload },
      )

      moodProfile.value = response.data.mood
      tracks.value = response.data.tracks
      suggestedMode.value = response.data.suggested_mode ?? null
    }
    catch (e: unknown) {
      error.value = e instanceof Error ? e.message : 'Une erreur est survenue.'
    }
    finally {
      loading.value = false
    }
  }

  async function fetchHistory(): Promise<void> {
    const response = await apiFetch<{ data: { data: MoodProfile[] } }>('/api/mood/history')
    history.value = response.data.data
  }

  return { form, moodProfile, tracks, history, loading, error, activityMode, suggestedMode, prediction, predictionLoading, setForm, setActivityMode, clearActivityMode, fetchPrediction, analyze, fetchHistory }
})
