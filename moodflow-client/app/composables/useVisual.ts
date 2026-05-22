type Theme = 'aurora' | 'storm' | 'sunset' | 'pulse'

interface Particle {
  x: number
  y: number
  vx: number
  vy: number
  baseSize: number
  opacity: number
  hue: number
  sat: number
  light: number
  life: number
  lifeSpeed: number
  angle: number
  angleSpeed: number
}

interface ThemeConfig {
  count: number
  hue: number
  hueRange: number
  sat: number
  light: number
  bgHue: number
  bgSat: number
  bgLight1: number
  bgLight2: number
  speed: number
  sizeBase: number
  sizeRange: number
  glow: number
  lifeSpeed: number // unités par ms
}

const TRANSITION_DURATION = 2000 // ms

const THEMES: Record<Theme, ThemeConfig> = {
  aurora: {
    count: 80,
    hue: 190, hueRange: 80,
    sat: 70, light: 65,
    bgHue: 220, bgSat: 50, bgLight1: 8, bgLight2: 3,
    speed: 0.25,
    sizeBase: 3, sizeRange: 2,
    glow: 14,
    lifeSpeed: 0.0007,
  },
  storm: {
    count: 150,
    hue: 10, hueRange: 25,
    sat: 80, light: 55,
    bgHue: 0, bgSat: 60, bgLight1: 5, bgLight2: 2,
    speed: 3.0,
    sizeBase: 1.8, sizeRange: 1.5,
    glow: 5,
    lifeSpeed: 0.003,
  },
  sunset: {
    count: 100,
    hue: 25, hueRange: 45,
    sat: 75, light: 60,
    bgHue: 20, bgSat: 40, bgLight1: 7, bgLight2: 3,
    speed: 0.45,
    sizeBase: 3.5, sizeRange: 2,
    glow: 10,
    lifeSpeed: 0.0009,
  },
  pulse: {
    count: 120,
    hue: 80, hueRange: 100,
    sat: 85, light: 65,
    bgHue: 100, bgSat: 45, bgLight1: 6, bgLight2: 2,
    speed: 1.5,
    sizeBase: 2, sizeRange: 2,
    glow: 18,
    lifeSpeed: 0.0015,
  },
}

// ── Helpers ──────────────────────────────────────────────────────────────────

function smoothstep(t: number): number {
  const c = Math.max(0, Math.min(1, t))
  return c * c * (3 - 2 * c)
}

function lerp(a: number, b: number, t: number): number {
  return a + (b - a) * t
}

function lerpConfig(a: ThemeConfig, b: ThemeConfig, t: number): ThemeConfig {
  return {
    count:     Math.round(lerp(a.count, b.count, t)),
    hue:       lerp(a.hue, b.hue, t),
    hueRange:  lerp(a.hueRange, b.hueRange, t),
    sat:       lerp(a.sat, b.sat, t),
    light:     lerp(a.light, b.light, t),
    bgHue:     lerp(a.bgHue, b.bgHue, t),
    bgSat:     lerp(a.bgSat, b.bgSat, t),
    bgLight1:  lerp(a.bgLight1, b.bgLight1, t),
    bgLight2:  lerp(a.bgLight2, b.bgLight2, t),
    speed:     lerp(a.speed, b.speed, t),
    sizeBase:  lerp(a.sizeBase, b.sizeBase, t),
    sizeRange: lerp(a.sizeRange, b.sizeRange, t),
    glow:      lerp(a.glow, b.glow, t),
    lifeSpeed: lerp(a.lifeSpeed, b.lifeSpeed, t),
  }
}

function determineTheme(energy: number, valence: number, intensity: number): Theme {
  if (energy > 70 && intensity > 70)  return 'storm'
  if (energy > 60 && valence > 0.3)   return 'pulse'
  if (valence < -0.3 && energy < 50)  return 'sunset'
  if (energy < 35 && valence > 0)     return 'aurora'
  return 'aurora'
}

// ── Composable ───────────────────────────────────────────────────────────────

export function useVisual(canvasRef: Ref<HTMLCanvasElement | null>) {
  let ctx: CanvasRenderingContext2D | null = null
  let rafId: number | null = null
  let lastTs = 0
  let W = 0
  let H = 0
  let resizeHandler: (() => void) | null = null

  let particles: Particle[] = []
  let audioData: Uint8Array = new Uint8Array(0)

  let frameCount = 0
  let readyCallback: (() => void) | null = null

  let currentTheme: Theme = 'aurora'
  let nextTheme: Theme = 'aurora'
  let transitionStart = 0

  // ── Canvas setup ────────────────────────────────────────────────────────

  function setupCanvas(): boolean {
    const canvas = canvasRef.value
    if (!canvas) return false
    ctx = canvas.getContext('2d')
    if (!ctx) return false
    const dpr = window.devicePixelRatio || 1
    W = window.innerWidth
    H = window.innerHeight
    canvas.width = W * dpr
    canvas.height = H * dpr
    ctx.scale(dpr, dpr)
    return true
  }

  // ── Particle factory ────────────────────────────────────────────────────

  function spawn(config: ThemeConfig, stagger = false): Particle {
    let x: number
    let y: number

    if (nextTheme === 'storm') {
      const edge = Math.floor(Math.random() * 4)
      if (edge === 0)      { x = Math.random() * W; y = 0 }
      else if (edge === 1) { x = W; y = Math.random() * H }
      else if (edge === 2) { x = Math.random() * W; y = H }
      else                 { x = 0; y = Math.random() * H }
    }
    else if (nextTheme === 'pulse') {
      const r = Math.random() * Math.min(W, H) * 0.2
      const a = Math.random() * Math.PI * 2
      x = W / 2 + Math.cos(a) * r
      y = H / 2 + Math.sin(a) * r
    }
    else {
      x = Math.random() * W
      y = Math.random() * H
    }

    const angle = Math.random() * Math.PI * 2
    const speed = config.speed * (0.7 + Math.random() * 0.6)

    return {
      x, y,
      vx: Math.cos(angle) * speed,
      vy: Math.sin(angle) * speed,
      baseSize:   config.sizeBase + (Math.random() - 0.5) * config.sizeRange,
      opacity:    0,
      hue:        config.hue + (Math.random() - 0.5) * config.hueRange,
      sat:        config.sat,
      light:      config.light,
      life:       stagger ? Math.random() : 0,
      lifeSpeed:  config.lifeSpeed * (0.8 + Math.random() * 0.4),
      angle:      Math.random() * Math.PI * 2,
      angleSpeed: (Math.random() - 0.5) * 0.025,
    }
  }

  function initParticles(config: ThemeConfig) {
    particles = Array.from({ length: config.count }, () => spawn(config, true))
  }

  // ── Audio ────────────────────────────────────────────────────────────────

  function getAudioMults(): { bass: number; mid: number } {
    if (!audioData.length) return { bass: 1, mid: 1 }
    let bassSum = 0
    for (let i = 0; i < 4; i++) bassSum += audioData[i] ?? 0
    let midSum = 0
    for (let i = 5; i < 15; i++) midSum += audioData[i] ?? 0
    return {
      bass: 1 + (bassSum / (4 * 255)) * 1.5,
      mid:  1 + (midSum / (10 * 255)) * 0.8,
    }
  }

  // ── Per-particle update ──────────────────────────────────────────────────

  function updateParticle(
    p: Particle,
    config: ThemeConfig,
    dt: number,
    bass: number,
    behavior: Theme,
  ) {
    p.angle += p.angleSpeed * dt

    switch (behavior) {
      case 'aurora':
        p.x += Math.cos(p.angle) * config.speed * dt * 0.05
        p.y += Math.sin(p.angle * 0.7) * config.speed * dt * 0.04
        break

      case 'storm':
        p.vx += (Math.random() - 0.5) * 0.4
        p.vy += (Math.random() - 0.5) * 0.4
        p.vx = Math.max(-config.speed, Math.min(config.speed, p.vx))
        p.vy = Math.max(-config.speed, Math.min(config.speed, p.vy))
        p.x += p.vx * bass * dt * 0.10
        p.y += p.vy * bass * dt * 0.10
        break

      case 'sunset':
        p.y -= config.speed * dt * 0.045
        p.x += Math.sin(p.angle) * config.speed * dt * 0.025
        break

      case 'pulse': {
        const dx = p.x - W / 2
        const dy = p.y - H / 2
        const dist = Math.sqrt(dx * dx + dy * dy) || 1
        const pulse = config.speed * (1 + Math.sin(p.angle * 2) * 0.35 * bass)
        p.x += (dx / dist) * pulse * dt * 0.07
        p.y += (dy / dist) * pulse * dt * 0.07
        break
      }
    }

    // Wrap around canvas edges
    if (p.x < -20)      p.x = W + 20
    else if (p.x > W + 20) p.x = -20
    if (p.y < -20)      p.y = H + 20
    else if (p.y > H + 20) p.y = -20

    // Life cycle : fade in → full → fade out
    p.life += p.lifeSpeed * dt
    if (p.life < 0.12)      p.opacity = (p.life / 0.12) * 0.85
    else if (p.life > 0.82) p.opacity = ((1 - p.life) / 0.18) * 0.85
    else                    p.opacity = 0.85
    p.opacity = Math.max(0, p.opacity)

    if (p.life >= 1) Object.assign(p, spawn(config))
  }

  // ── Draw ─────────────────────────────────────────────────────────────────

  function drawBackground(config: ThemeConfig) {
    if (!ctx) return
    const grad = ctx.createRadialGradient(
      W * 0.4, H * 0.35, 0,
      W * 0.5, H * 0.5,  Math.max(W, H) * 0.75,
    )
    grad.addColorStop(0, `hsl(${config.bgHue}, ${config.bgSat}%, ${config.bgLight1}%)`)
    grad.addColorStop(1, `hsl(${config.bgHue}, ${Math.round(config.bgSat * 0.5)}%, ${config.bgLight2}%)`)
    ctx.fillStyle = grad
    ctx.fillRect(0, 0, W, H)
  }

  // ── Animation loop ───────────────────────────────────────────────────────

  function frame(ts: number) {
    if (!ctx) return

    const dt = Math.min(ts - lastTs, 100)
    lastTs = ts

    // Transition progress
    const raw = currentTheme === nextTheme
      ? 1
      : Math.min((ts - transitionStart) / TRANSITION_DURATION, 1)
    const t = smoothstep(raw)

    if (raw >= 1 && currentTheme !== nextTheme) currentTheme = nextTheme

    const config = raw < 1
      ? lerpConfig(THEMES[currentTheme], THEMES[nextTheme], t)
      : THEMES[currentTheme]

    const activeBehavior: Theme = raw > 0.5 ? nextTheme : currentTheme

    // Adjust particle count progressively
    while (particles.length < config.count) particles.push(spawn(config))
    if (particles.length > config.count) particles.length = config.count

    const { bass, mid } = getAudioMults()

    drawBackground(config)

    ctx.shadowBlur = config.glow
    for (const p of particles) {
      updateParticle(p, config, dt, bass, activeBehavior)
      ctx.globalAlpha  = p.opacity
      ctx.shadowColor  = `hsl(${p.hue}, ${p.sat}%, ${p.light}%)`
      ctx.fillStyle    = `hsl(${p.hue}, ${p.sat}%, ${p.light}%)`
      ctx.beginPath()
      ctx.arc(p.x, p.y, Math.max(0.5, p.baseSize * mid), 0, Math.PI * 2)
      ctx.fill()
    }
    ctx.globalAlpha = 1
    ctx.shadowBlur  = 0

    frameCount++
    if (frameCount === 3 && readyCallback) {
      readyCallback()
      readyCallback = null
    }

    rafId = requestAnimationFrame(frame)
  }

  // ── Resize ───────────────────────────────────────────────────────────────

  function setupResize() {
    resizeHandler = () => {
      setTimeout(() => {
        if (!setupCanvas()) return
        for (const p of particles) {
          p.x = Math.min(p.x, W)
          p.y = Math.min(p.y, H)
        }
      }, 100)
    }
    window.addEventListener('resize', resizeHandler)
  }

  // ── Public API ───────────────────────────────────────────────────────────

  function start(onReady?: () => void) {
    if (!import.meta.client) return
    readyCallback = onReady ?? null
    if (!setupCanvas()) {
      if (readyCallback) { readyCallback(); readyCallback = null }
      return
    }
    initParticles(THEMES[currentTheme])
    lastTs = performance.now()
    frameCount = 0
    rafId = requestAnimationFrame(frame)
    setupResize()
  }

  function stop() {
    if (rafId !== null) { cancelAnimationFrame(rafId); rafId = null }
    if (resizeHandler) { window.removeEventListener('resize', resizeHandler); resizeHandler = null }
  }

  function updateMood(energy: number, valence: number, intensity: number) {
    const theme = determineTheme(energy, valence, intensity)
    if (theme === nextTheme) return
    currentTheme = nextTheme // snap au target courant avant de repartir
    nextTheme = theme
    transitionStart = performance.now()
  }

  function updateAudioData(data: Uint8Array) {
    audioData = data
  }

  return { start, stop, updateMood, updateAudioData }
}
