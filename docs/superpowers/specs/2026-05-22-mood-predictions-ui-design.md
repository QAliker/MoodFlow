# Mood Predictions UI — Design Spec

**Date:** 2026-05-22  
**Scope:** `moodflow-client/` only — backend `GET /api/mood/predict` already exists.

---

## Goal

On page load of `/mood`, fetch a mood prediction in the background. Display contextual UI depending on confidence level, allowing the user to pre-fill sliders from the prediction or ignore it.

---

## API Contract

`GET /api/mood/predict?lat=&lon=` (lat/lon optional)

Response shape:
```ts
{
  predicted_mood: { energy: number; valence: number; intensity: number }
  confidence: number       // 0.0 to 1.0
  based_on: number         // number of past moods used
  suggested_activity: string | null
  message: string          // e.g. "Vendredi soir, tu es généralement énergique"
}
```

Confidence < 0.3 → backend still returns data but confidence is 0.0 and based_on is 0. Frontend treats this as "no prediction available".

---

## Architecture

**Approach B:** prediction state in store + dedicated `MoodPredictionBanner` component.

### Files changed/created

| File | Change |
|------|--------|
| `stores/mood.ts` | Add `MoodPrediction` interface, `prediction`, `predictionLoading` state, `fetchPrediction()` action |
| `components/mood/PredictionBanner.vue` | New — banner with dismiss/apply logic |
| `pages/mood.vue` | Wire prediction fetch, render banner + hint + dashed border |

---

## Store (`stores/mood.ts`)

### New interface
```ts
export interface MoodPrediction {
  predicted_mood: { energy: number; valence: number; intensity: number }
  confidence: number
  based_on: number
  suggested_activity: string | null
  message: string
}
```

### New state
```ts
const prediction = ref<MoodPrediction | null>(null)
const predictionLoading = ref(false)
```

### New action
```ts
async function fetchPrediction(coords?: { lat: number; lon: number } | null): Promise<void>
```
- Calls `GET /api/mood/predict` with optional `lat`/`lon` query params
- Stores result in `prediction`
- Silent error handling — prediction is non-critical, must not block the page
- Expose via store return

---

## Component: `MoodPredictionBanner.vue`

**Condition:** rendered when `confidence > 0.5` and not dismissed.

**Props:**
```ts
defineProps<{ prediction: MoodPrediction }>()
```

**Emits:**
```ts
defineEmits<{
  apply: []    // user clicked "Oui"
  dismiss: []  // user clicked "Non merci"
}>()
```

**Local state:** `dismissed = ref(false)` — closes banner without touching store.

**UI:** `UAlert` component:
- `icon="i-heroicons-sparkles"`, `color="primary"`, `variant="subtle"`
- Title: `"🔮 " + prediction.message`
- Description: `"Utiliser cette prédiction ?"`
- Actions slot: two `UButton` — `"Oui"` (primary) and `"Non merci"` (ghost)

**Animation:** `<Transition>` wrapping the `UAlert`:
- `enter-active-class="transition duration-500 ease-out"`
- `enter-from-class="opacity-0 -translate-y-2"`
- `enter-to-class="opacity-100 translate-y-0"`

---

## Page: `pages/mood.vue`

### Prediction fetch on mount

```ts
onMounted(() => {
  fetchWeather()
  moodStore.fetchPrediction(coords.value)
})

// Re-try once coords resolve (weather-aware prediction)
watch(coords, (c) => {
  if (c && !moodStore.prediction) moodStore.fetchPrediction(c)
}, { once: true })
```

### Banner placement

Between the page header and the `UCard`, so it's visible on all tabs:

```html
<MoodPredictionBanner
  v-if="showBanner"
  :prediction="moodStore.prediction!"
  @apply="onApplyPrediction"
  @dismiss="bannerDismissed = true"
/>
```

```ts
const bannerDismissed = ref(false)
const showBanner = computed(() =>
  moodStore.prediction !== null &&
  moodStore.prediction.confidence > 0.5 &&
  !bannerDismissed.value
)
```

### Apply handler

```ts
function onApplyPrediction() {
  const { energy, valence, intensity } = moodStore.prediction!.predicted_mood
  moodStore.setForm({ source: 'slider', emotion: null, energy, valence, intensity })
  if (moodStore.prediction!.suggested_activity) {
    moodStore.setActivityMode(moodStore.prediction!.suggested_activity)
  }
  activeTab.value = 'slider'
  bannerDismissed.value = true
  isPredicted.value = true
}
```

### Hint (confidence 0.3–0.5)

Inside `<template #slider>`, after the emoji/label feedback block:

```html
<p v-if="showHint" class="text-center text-xs text-gray-500 italic">
  {{ moodStore.prediction!.message }}
</p>
```

```ts
const showHint = computed(() =>
  moodStore.prediction !== null &&
  moodStore.prediction.confidence >= 0.3 &&
  moodStore.prediction.confidence <= 0.5
)
```

### Dashed border on predicted sliders

```ts
const isPredicted = ref(false)
```

Wrapper around `<MoodSliders>`:
```html
<div :class="isPredicted ? 'rounded-xl border border-dashed border-primary-500/50 p-3 transition-all duration-300' : ''">
  <MoodSliders
    :model-value="sliderValues"
    @update:model-value="onSlidersUpdate"
  />
</div>
```

`isPredicted` resets to `false` when the user manually moves a slider — modify `onSlidersUpdate`:
```ts
function onSlidersUpdate(values: ...) {
  isPredicted.value = false
  moodStore.setForm({ ... })
}
```

---

## Confidence Thresholds Summary

| Confidence | Behavior |
|-----------|----------|
| > 0.5 | Banner (`UAlert`) at top of page. "Oui" pre-fills sliders + switches to slider tab. |
| 0.3–0.5 | Subtle hint text inside slider tab only. No pre-fill. |
| < 0.3 | Nothing shown. Normal behavior. |

---

## Error Handling

- `fetchPrediction` catches all errors silently — no toast, no blocking state.
- If prediction is null, all computed flags (`showBanner`, `showHint`, `isPredicted`) default to false.
