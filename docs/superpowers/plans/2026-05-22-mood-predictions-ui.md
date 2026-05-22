# Mood Predictions UI — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Display mood predictions from `GET /api/mood/predict` on the `/mood` page with a dismissible banner (confidence > 0.5), a subtle hint (confidence 0.3–0.5), and visually distinct dashed-border sliders when a prediction is applied.

**Architecture:** Prediction state lives in the Pinia mood store (`stores/mood.ts`). A dedicated `MoodPredictionBanner.vue` component handles the high-confidence banner UI with its own dismiss logic. The hint and dashed-border wrapper are inline in `mood.vue`.

**Tech Stack:** Nuxt 4, Vue 3.4 (Composition API + `<script setup>`), Pinia, NuxtUI v3 (`UAlert`, `UButton`), TypeScript strict.

---

## File Map

| Action | Path | Responsibility |
|--------|------|----------------|
| Modify | `moodflow-client/app/stores/mood.ts` | Add `MoodPrediction` interface, `prediction`/`predictionLoading` state, `fetchPrediction()` action |
| Create | `moodflow-client/app/components/mood/PredictionBanner.vue` | Dismissible banner for confidence > 0.5 |
| Modify | `moodflow-client/app/pages/mood.vue` | Fetch prediction on mount, render banner + hint + dashed border |

---

## Task 1: Add prediction state and action to mood store

**Files:**
- Modify: `moodflow-client/app/stores/mood.ts`

- [ ] **Step 1: Add `MoodPrediction` interface after existing interfaces (line ~31)**

In `moodflow-client/app/stores/mood.ts`, after the `MoodForm` interface block, add:

```ts
export interface MoodPrediction {
  predicted_mood: { energy: number; valence: number; intensity: number }
  confidence: number
  based_on: number
  suggested_activity: string | null
  message: string
}
```

- [ ] **Step 2: Add `prediction` and `predictionLoading` refs inside the store (after `suggestedMode` ref, ~line 61)**

```ts
const prediction = ref<MoodPrediction | null>(null)
const predictionLoading = ref(false)
```

- [ ] **Step 3: Add `fetchPrediction` action (after `clearActivityMode`, before `analyze`)**

```ts
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
```

- [ ] **Step 4: Expose new state and action in the store return (line 115)**

Replace the existing return:
```ts
return { form, moodProfile, tracks, history, loading, error, activityMode, suggestedMode, setForm, setActivityMode, clearActivityMode, analyze, fetchHistory }
```
With:
```ts
return { form, moodProfile, tracks, history, loading, error, activityMode, suggestedMode, prediction, predictionLoading, setForm, setActivityMode, clearActivityMode, fetchPrediction, analyze, fetchHistory }
```

- [ ] **Step 5: Verify types**

```bash
cd moodflow-client && docker compose run --rm --no-deps client npm run typecheck
```

Expected: no errors related to `mood.ts`.

- [ ] **Step 6: Commit**

```bash
git add moodflow-client/app/stores/mood.ts
git commit -m "feat(store): add MoodPrediction type and fetchPrediction action"
```

---

## Task 2: Create `MoodPredictionBanner.vue`

**Files:**
- Create: `moodflow-client/app/components/mood/PredictionBanner.vue`

- [ ] **Step 1: Create the component file**

Create `moodflow-client/app/components/mood/PredictionBanner.vue` with this content:

```vue
<script setup lang="ts">
import type { MoodPrediction } from '~/stores/mood'

defineProps<{ prediction: MoodPrediction }>()

const emit = defineEmits<{
  apply: []
  dismiss: []
}>()
</script>

<template>
  <Transition
    enter-active-class="transition duration-500 ease-out"
    enter-from-class="opacity-0 -translate-y-2"
    enter-to-class="opacity-100 translate-y-0"
    appear
  >
    <UAlert
      icon="i-heroicons-sparkles"
      color="primary"
      variant="subtle"
      :title="`🔮 ${prediction.message}`"
      description="Utiliser cette prédiction ?"
      orientation="horizontal"
    >
      <template #actions>
        <UButton size="sm" color="primary" variant="solid" @click="emit('apply')">
          Oui
        </UButton>
        <UButton size="sm" color="neutral" variant="ghost" @click="emit('dismiss')">
          Non merci
        </UButton>
      </template>
    </UAlert>
  </Transition>
</template>
```

- [ ] **Step 2: Verify types**

```bash
cd moodflow-client && docker compose run --rm --no-deps client npm run typecheck
```

Expected: no errors.

- [ ] **Step 3: Commit**

```bash
git add moodflow-client/app/components/mood/PredictionBanner.vue
git commit -m "feat(components): add MoodPredictionBanner component"
```

---

## Task 3: Wire prediction into `mood.vue`

**Files:**
- Modify: `moodflow-client/app/pages/mood.vue`

- [ ] **Step 1: Add prediction fetch on mount**

In the `<script setup>` block, replace:
```ts
onMounted(fetchWeather)
```
With:
```ts
onMounted(() => {
  fetchWeather()
  moodStore.fetchPrediction(coords.value)
})

// Re-fetch once GPS coords resolve (enables weather-aware prediction)
watch(coords, (c) => {
  if (c && !moodStore.prediction) moodStore.fetchPrediction(c)
}, { once: true })
```

- [ ] **Step 2: Add banner and hint computed state**

After the existing `const showOverlay = computed(...)` line, add:

```ts
const bannerDismissed = ref(false)
const isPredicted = ref(false)

const showBanner = computed(() =>
  moodStore.prediction !== null &&
  moodStore.prediction.confidence > 0.5 &&
  !bannerDismissed.value,
)

const showHint = computed(() =>
  moodStore.prediction !== null &&
  moodStore.prediction.confidence >= 0.3 &&
  moodStore.prediction.confidence <= 0.5,
)
```

- [ ] **Step 3: Add `onApplyPrediction` handler**

After the existing `onActivityDefaults` function, add:

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

- [ ] **Step 4: Reset `isPredicted` when user moves a slider manually**

Replace the existing `onSlidersUpdate` function:
```ts
function onSlidersUpdate(values: { energy: number; valence: number; intensity: number }) {
  moodStore.setForm({
    source:    'slider',
    emotion:   null,
    energy:    values.energy,
    valence:   values.valence,
    intensity: values.intensity,
  })
}
```
With:
```ts
function onSlidersUpdate(values: { energy: number; valence: number; intensity: number }) {
  isPredicted.value = false
  moodStore.setForm({
    source:    'slider',
    emotion:   null,
    energy:    values.energy,
    valence:   values.valence,
    intensity: values.intensity,
  })
}
```

- [ ] **Step 5: Add banner in template — between header and UCard**

In `<template>`, locate the header block:
```html
    <!-- Header -->
    <div class="mb-10 text-center space-y-2">
      <h1 class="text-3xl font-bold text-white">Comment tu te sens ?</h1>
      <p class="text-gray-400 text-sm">Décris ton mood pour obtenir une playlist personnalisée.</p>
    </div>
```

Add the banner immediately after it (before `<UCard>`):
```html
    <!-- Prediction banner -->
    <MoodPredictionBanner
      v-if="showBanner"
      :prediction="moodStore.prediction!"
      class="mb-6"
      @apply="onApplyPrediction"
      @dismiss="bannerDismissed = true"
    />
```

- [ ] **Step 6: Add dashed border wrapper around MoodSliders**

In `<template #slider>`, locate:
```html
              <MoodSliders
                :model-value="sliderValues"
                @update:model-value="onSlidersUpdate"
              />
```

Replace with:
```html
              <div :class="isPredicted ? 'rounded-xl border border-dashed border-primary-500/50 p-3 transition-all duration-300' : ''">
                <MoodSliders
                  :model-value="sliderValues"
                  @update:model-value="onSlidersUpdate"
                />
              </div>
```

- [ ] **Step 7: Add hint text in slider tab**

In `<template #slider>`, locate the emoji/label feedback block:
```html
              <!-- Feature 2 : feedback emoji/emotion -->
              <div class="flex items-center justify-center gap-3 py-2">
                <span class="text-4xl leading-none transition-all duration-300">{{ sliderMood.emoji }}</span>
                <span class="text-lg font-semibold text-white">{{ sliderMood.label }}</span>
              </div>
```

Add the hint immediately after it:
```html
              <!-- Prediction hint (confidence 0.3–0.5) -->
              <p v-if="showHint" class="text-center text-xs text-gray-500 italic -mt-2">
                {{ moodStore.prediction!.message }}
              </p>
```

- [ ] **Step 8: Run typecheck and lint**

```bash
cd moodflow-client && docker compose run --rm --no-deps client npm run typecheck
```

Expected: no errors.

- [ ] **Step 9: Commit**

```bash
git add moodflow-client/app/pages/mood.vue
git commit -m "feat(mood): integrate prediction banner, hint, and suggested sliders"
```

---

## Task 4: Manual smoke test

- [ ] **Step 1: Start services**

```bash
docker compose up -d
```

- [ ] **Step 2: Test confidence > 0.5 flow**

Visit `http://localhost:3000/mood`. If the user has enough mood history (≥ 3 similar past moods), the banner appears at the top. Click "Oui" → page switches to Sliders tab, sliders update, dashed border appears around slider area. Move any slider → dashed border disappears.

- [ ] **Step 3: Test dismiss flow**

Reload page. If banner shows, click "Non merci" → banner disappears, sliders remain at default values.

- [ ] **Step 4: Test hint flow (confidence 0.3–0.5)**

If needed, inspect network tab for `GET /api/mood/predict` response. If confidence is between 0.3 and 0.5, switch to Sliders tab — a small italic text appears below the emoji feedback. No banner shown.

- [ ] **Step 5: Test no-prediction flow (confidence < 0.3)**

With a new account or no mood history, `confidence` returns 0.0. No banner, no hint, normal behavior.
