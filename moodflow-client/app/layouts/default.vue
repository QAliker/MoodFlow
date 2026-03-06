<script setup lang="ts">
const moodBg = useState<string>('moodBg', () => '')
</script>

<template>
  <div
    class="min-h-screen transition-all duration-700"
    :class="moodBg ? '' : 'bg-gray-950'"
    :style="moodBg ? { background: moodBg } : {}"
  >
    <AppNavbar />
    <main>
      <slot />
    </main>
  </div>
</template>
