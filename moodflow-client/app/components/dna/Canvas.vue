<!-- moodflow-client/app/components/dna/Canvas.vue -->
<script setup lang="ts">
import type { MoodDna } from '~/stores/dna'

const props = withDefaults(defineProps<{
  dna: Pick<MoodDna, 'shape' | 'colors' | 'pulseSpeed' | 'symmetry' | 'consistency'>
  size?: number
}>(), { size: 400 })

const canvasEl = ref<HTMLCanvasElement | null>(null)

let ctx: CanvasRenderingContext2D | null = null
let rafId: number | null = null
let lastTs = 0
let time = 0
let rotation = 0
let phase = 0

interface OrbitParticle {
  angle: number
  speed: number
  radius: number
  colorIdx: number
  size: number
  opacity: number
}

let particles: OrbitParticle[] = []

// ── Helpers ──────────────────────────────────────────────────────────────────

function hexToRgba(hex: string | undefined, alpha: number): string {
  if (!hex || hex.length < 7) return `rgba(100, 100, 200, ${alpha})`
  const r = parseInt(hex.slice(1, 3), 16)
  const g = parseInt(hex.slice(3, 5), 16)
  const b = parseInt(hex.slice(5, 7), 16)
  if (isNaN(r) || isNaN(g) || isNaN(b)) return `rgba(100, 100, 200, ${alpha})`
  return `rgba(${r}, ${g}, ${b}, ${alpha})`
}

// ── Setup ─────────────────────────────────────────────────────────────────────

function setupCanvas(): boolean {
  const canvas = canvasEl.value
  if (!canvas) return false
  ctx = canvas.getContext('2d')
  if (!ctx) return false
  const dpr = window.devicePixelRatio || 1
  const s = props.size
  canvas.width = s * dpr
  canvas.height = s * dpr
  canvas.style.width = `${s}px`
  canvas.style.height = `${s}px`
  ctx.setTransform(dpr, 0, 0, dpr, 0, 0)
  return true
}

function initParticles(): void {
  const baseRadius = props.size * 0.28
  const count = Math.min(Math.round(6 + (props.dna.consistency / 60) * 18), 24)
  particles = Array.from({ length: count }, (_, i) => ({
    angle:    (i / count) * Math.PI * 2,
    speed:    (0.0003 + Math.random() * 0.0003) * (Math.random() > 0.5 ? 1 : -1),
    radius:   baseRadius * (1.35 + Math.random() * 0.5),
    colorIdx: i % 4,
    size:     2 + Math.random() * 3,
    opacity:  0.4 + Math.random() * 0.5,
  }))
}

// ── Draw ─────────────────────────────────────────────────────────────────────

function drawBackground(s: number): void {
  if (!ctx) return
  const cx = s / 2
  const cy = s / 2
  const grad = ctx.createRadialGradient(cx, cy * 0.7, 0, cx, cy, s * 0.75)
  grad.addColorStop(0, '#111827')
  grad.addColorStop(1, '#030712')
  ctx.fillStyle = grad
  ctx.fillRect(0, 0, s, s)
}

function drawPolygon(s: number): void {
  if (!ctx) return
  const { shape, colors, pulseSpeed, symmetry } = props.dna
  const cx = s / 2
  const cy = s / 2
  const baseRadius = s * 0.28
  const pulse = 1 + 0.08 * Math.sin(time * pulseSpeed * 0.001)
  const irregularity = (1 - symmetry) * 0.25

  ctx.beginPath()
  for (let i = 0; i <= shape; i++) {
    const a = (i / shape) * Math.PI * 2 + rotation
    const dist = baseRadius * pulse * (1 + irregularity * Math.sin(i * 2.5 + phase))
    const x = cx + Math.cos(a) * dist
    const y = cy + Math.sin(a) * dist
    if (i === 0) ctx.moveTo(x, y)
    else ctx.lineTo(x, y)
  }
  ctx.closePath()

  const grad = ctx.createRadialGradient(cx, cy, 0, cx, cy, baseRadius * 1.15)
  grad.addColorStop(0,    hexToRgba(colors[0], 0.85))
  grad.addColorStop(0.4,  hexToRgba(colors[1], 0.6))
  grad.addColorStop(0.75, hexToRgba(colors[2], 0.35))
  grad.addColorStop(1,    hexToRgba(colors[3], 0.1))

  ctx.shadowBlur  = 40
  ctx.shadowColor = colors[0]
  ctx.fillStyle   = grad
  ctx.fill()

  ctx.shadowBlur  = 20
  ctx.shadowColor = colors[1]
  ctx.strokeStyle = hexToRgba(colors[1], 0.8)
  ctx.lineWidth   = 2
  ctx.stroke()

  ctx.shadowBlur = 0
}

function drawParticles(dt: number): void {
  if (!ctx) return
  const { colors } = props.dna
  const cx = props.size / 2
  const cy = props.size / 2

  for (const p of particles) {
    p.angle += p.speed * dt
    const x = cx + Math.cos(p.angle) * p.radius
    const y = cy + Math.sin(p.angle) * p.radius
    ctx.globalAlpha = p.opacity
    ctx.shadowBlur  = 8
    ctx.shadowColor = colors[p.colorIdx] ?? colors[0]
    ctx.fillStyle   = colors[p.colorIdx] ?? colors[0]
    ctx.beginPath()
    ctx.arc(x, y, p.size, 0, Math.PI * 2)
    ctx.fill()
  }
  ctx.globalAlpha = 1
  ctx.shadowBlur  = 0
}

// ── Animation loop ────────────────────────────────────────────────────────────

function frame(ts: number): void {
  if (!ctx) return
  const dt = Math.min(ts - lastTs, 100)
  lastTs = ts
  time      += dt
  rotation  += 0.00008 * dt
  phase     += 0.0004 * dt

  const s = props.size
  ctx.clearRect(0, 0, s, s)
  drawBackground(s)
  drawPolygon(s)
  drawParticles(dt)

  rafId = requestAnimationFrame(frame)
}

function start(): void {
  if (!import.meta.client) return
  if (!setupCanvas()) return
  initParticles()
  lastTs = performance.now()
  rafId = requestAnimationFrame(frame)
}

function stop(): void {
  if (rafId !== null) { cancelAnimationFrame(rafId); rafId = null }
}

function downloadImage(): void {
  const canvas = canvasEl.value
  if (!canvas) return
  try {
    const url = canvas.toDataURL('image/png')
    const a = document.createElement('a')
    a.href = url
    a.download = 'mood-dna.png'
    a.click()
  }
  catch {
    // canvas may be tainted by cross-origin content
  }
}

watch(() => props.dna.consistency, () => initParticles())

onMounted(start)
onUnmounted(stop)

defineExpose({ downloadImage })
</script>

<template>
  <div class="rounded-2xl overflow-hidden shadow-2xl">
    <canvas ref="canvasEl" aria-hidden="true" />
  </div>
</template>
