// Module-level singletons — un seul AudioContext/HTMLAudioElement pour toute l'app
let audioEl: HTMLAudioElement | null = null
let audioCtx: AudioContext | null = null
let analyser: AnalyserNode | null = null

const isPlaying = ref(false)
const progress = ref(0)       // 0-100
const currentTime = ref(0)    // secondes
const duration = ref(0)       // secondes
const isEnded = ref(false)    // true quand le morceau se termine naturellement

function getAudioEl(): HTMLAudioElement {
  if (audioEl) return audioEl

  audioEl = new Audio()
  audioEl.crossOrigin = 'anonymous'

  audioEl.addEventListener('timeupdate', () => {
    if (!audioEl!.duration) return
    currentTime.value = audioEl!.currentTime
    progress.value = (audioEl!.currentTime / audioEl!.duration) * 100
  })
  audioEl.addEventListener('loadedmetadata', () => {
    duration.value = audioEl!.duration
  })
  audioEl.addEventListener('ended', () => {
    isPlaying.value = false
    progress.value = 0
    currentTime.value = 0
    isEnded.value = true
  })
  audioEl.addEventListener('pause', () => {
    isPlaying.value = false
  })

  return audioEl
}

function setupAudioContext() {
  if (audioCtx) return
  try {
    audioCtx = new (window.AudioContext || (window as unknown as { webkitAudioContext: typeof AudioContext }).webkitAudioContext)()
    analyser = audioCtx.createAnalyser()
    analyser.fftSize = 256
    const source = audioCtx.createMediaElementSource(getAudioEl())
    source.connect(analyser)
    analyser.connect(audioCtx.destination)
  }
  catch {
    // CORS ou AudioContext non disponible — dégradation gracieuse
    audioCtx = null
    analyser = null
  }
}

export function useAudio() {
  async function play(url?: string): Promise<void> {
    if (!import.meta.client) return
    const a = getAudioEl()

    if (url && a.src !== url) {
      a.src = url
      progress.value = 0
      currentTime.value = 0
      isEnded.value = false
    }

    setupAudioContext()
    if (audioCtx?.state === 'suspended') {
      await audioCtx.resume()
    }

    try {
      await a.play()
      isPlaying.value = true
      isEnded.value = false
    }
    catch {
      isPlaying.value = false
    }
  }

  function pause(): void {
    audioEl?.pause()
    isPlaying.value = false
  }

  function seek(pct: number): void {
    if (!audioEl?.duration) return
    audioEl.currentTime = (pct / 100) * audioEl.duration
    isEnded.value = false
  }

  // Pour la visualisation Canvas future
  function getFrequencyData(): Uint8Array {
    if (!analyser) return new Uint8Array(0)
    const data = new Uint8Array(analyser.frequencyBinCount)
    analyser.getByteFrequencyData(data)
    return data
  }

  return { isPlaying, progress, currentTime, duration, isEnded, play, pause, seek, getFrequencyData }
}
