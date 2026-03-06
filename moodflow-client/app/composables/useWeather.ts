export interface WeatherData {
  condition: 'clear' | 'clouds' | 'rain' | 'snow'
  temperature: number
  description: string
}

export interface Coords {
  lat: number
  lon: number
}

export function useWeather() {
  const apiFetch = useApiFetch()

  const weather = ref<WeatherData | null>(null)
  const coords = ref<Coords | null>(null)
  const loading = ref(false)
  const error = ref<string | null>(null)

  function getCoords(): Promise<Coords> {
    return new Promise((resolve, reject) => {
      if (!navigator.geolocation) {
        reject(new Error('Géolocalisation non supportée'))
        return
      }
      navigator.geolocation.getCurrentPosition(
        pos => resolve({ lat: pos.coords.latitude, lon: pos.coords.longitude }),
        () => reject(new Error('Géolocalisation refusée')),
        { timeout: 8000 },
      )
    })
  }

  async function fetch(): Promise<void> {
    if (!import.meta.client) return
    loading.value = true
    error.value = null

    try {
      const position = await getCoords()
      coords.value = position

      const res = await apiFetch<{ data: WeatherData | null }>(
        `/api/weather?lat=${position.lat}&lon=${position.lon}`,
      )
      weather.value = res.data
    }
    catch (e) {
      // Refus géoloc ou API indisponible — non bloquant
      error.value = e instanceof Error ? e.message : 'Météo indisponible'
    }
    finally {
      loading.value = false
    }
  }

  return { weather, coords, loading, error, fetch }
}
