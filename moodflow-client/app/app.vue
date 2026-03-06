<script setup lang="ts">
useHead({ bodyAttrs: { class: 'bg-gray-950' } })
</script>

<template>
  <UApp>
    <NuxtRouteAnnouncer />
    <NuxtLayout>
      <NuxtPage />
    </NuxtLayout>
  </UApp>
</template>
